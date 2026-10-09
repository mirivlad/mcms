#!/usr/bin/env python3
"""Exercise package review/install/enable and persistence on a disposable stack."""
import argparse
import html
import http.cookiejar
import io
import json
import re
import time
import urllib.error
import urllib.parse
import urllib.request
import zipfile

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', default='http://127.0.0.1:8080')
parser.add_argument('--cookies', required=True)
parser.add_argument('--phase', choices=['install', 'persist'], default='install')
args = parser.parse_args()
base = args.base_url.rstrip('/')
jar = http.cookiejar.MozillaCookieJar(args.cookies)
jar.load(ignore_discard=True, ignore_expires=True)
owner = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
manifest = {
    'id': 'package_probe', 'name': 'Package probe', 'version': '1.0.0',
    'namespace': 'MCMS\\Modules\\PackageProbe', 'provider': 'MCMS\\Modules\\PackageProbe\\Module',
    'mcms': '^0.1', 'public_paths': ['package-probe'],
}
files = {
    'Module.php': '''<?php
namespace MCMS\\Modules\\PackageProbe;
final class Module implements \\MCMS\\Core\\Modules\\ModuleProviderInterface {
    public function boot(\\MCMS\\Core\\Modules\\ModuleContext $context): void {}
    public function routes(\\CodeIgniter\\Router\\RouteCollection $routes): void {
        $routes->get('package-probe', '\\MCMS\\Modules\\PackageProbe\\Controllers\\Probe::index');
    }
}
''',
    'Controllers/Probe.php': '''<?php
namespace MCMS\\Modules\\PackageProbe\\Controllers;
final class Probe extends \\App\\Controllers\\BaseController {
    public function index(): string {
        $rows = db_connect()->table('package_probe_records')->get()->getResultArray();
        return 'package-probe:' . $rows[0]['value'] . ':' . count($rows);
    }
}
''',
    'Database/Migrations/2026-10-09-000001_CreateProbe.php': '''<?php
namespace MCMS\\Modules\\PackageProbe\\Database\\Migrations;
final class CreateProbe extends \\CodeIgniter\\Database\\Migration {
    public function up(): void {
        $this->forge->addField(['id' => ['type' => 'INT', 'auto_increment' => true], 'value' => ['type' => 'VARCHAR', 'constraint' => 64]]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('package_probe_records');
        $this->db->table('package_probe_records')->insert(['value' => 'preserved-record']);
    }
    public function down(): void { $this->forge->dropTable('package_probe_records'); }
}
''',
}


def page(path, client=owner):
    response = client.open(base + path, timeout=30)
    return response.read().decode(), response.url


def csrf(path, client=owner):
    return html.unescape(re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', page(path, client)[0])[1])


def post(path, fields, form='/admin/modules/package', client=owner):
    pairs = list(fields.items()) if isinstance(fields, dict) else fields
    response = client.open(base + path, urllib.parse.urlencode(pairs + [('csrf_test_name', csrf(form, client))]).encode(), timeout=30)
    return response.read().decode(), response.url


def archive(changes=None, extra=None):
    output = io.BytesIO()
    with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED) as package:
        package.writestr('module.json', json.dumps({**manifest, **(changes or {})}))
        for name, content in {**files, **(extra or {})}.items():
            package.writestr(name, content)
    return output.getvalue()


def upload(content, client=owner, form='/admin/modules/package'):
    boundary = 'mcms-package-smoke-boundary'
    token = csrf(form, client)
    payload = (f'--{boundary}\r\nContent-Disposition: form-data; name="csrf_test_name"\r\n\r\n{token}\r\n'
               f'--{boundary}\r\nContent-Disposition: form-data; name="package"; filename="probe.zip"\r\n'
               'Content-Type: application/zip\r\n\r\n').encode() + content + f'\r\n--{boundary}--\r\n'.encode()
    request = urllib.request.Request(base + '/admin/modules/package', payload, {'Content-Type': 'multipart/form-data; boundary=' + boundary})
    response = client.open(request, timeout=30)
    return response.read().decode(), response.url


def absent():
    try:
        page('/package-probe')
        raise AssertionError('Inactive package route is active')
    except urllib.error.HTTPError as error:
        assert error.code == 404, error.code


if args.phase == 'persist':
    assert page('/package-probe')[0] == 'package-probe:preserved-record:1'
    assert 'Package probe' in page('/admin/modules')[0]
    post('/admin/modules/package_probe/disable', {}, '/admin/modules')
    absent()
    post('/admin/modules/package_probe/enable', {}, '/admin/modules')
    assert page('/package-probe')[0] == 'package-probe:preserved-record:1'
    print('Package and migration data survived container recreation and disable/re-enable.')
else:
    assert '/admin/modules/package' in page('/admin/modules')[0]
    for content, message in [
        (archive(extra={'../outside.php': '<?php'}), 'Unsafe package path'),
        (archive({'mcms': '^99.0'}), 'requires mCMS'),
        (archive(extra={'Config/Routes.php': '<?php'}), 'auto-discovered'),
    ]:
        assert message in upload(content)[0]
    absent()
    preview = upload(archive())[0]
    assert 'Package preview' in preview and 'SHA-256' in preview
    token = re.search(r'name="token" value="([^"]+)"', preview)[1]
    # A preview requires a matching server-owned token and explicit trust.
    assert 'confirm that you trust' in post('/admin/modules/package/install', {'token': token})[0]
    assert 'confirm that you trust' in post('/admin/modules/package/install', {'token': '0' * 48, 'trusted': '1'})[0]
    absent()
    # Ordinary administrative users cannot inspect/install code or cancel the
    # owner's pending package, including when submitting a valid CSRF token.
    reader_name = 'package.reader.' + str(time.time_ns())[-8:]
    reader_email = reader_name + '@example.test'
    source = post('/admin/users/create', {
        'username': reader_name, 'email': reader_email, 'password': 'Package-Reader-2026!',
    }, '/admin/users/create')[0]
    row = next(row for row in re.findall(r'<tr\b.*?</tr>', source, re.S) if reader_name in row)
    user_id = re.search(r'/admin/users/(\d+)/access', row)[1]
    access = '/admin/users/' + user_id + '/access'
    post(access, [('permissions[]', 'core.admin'), ('permissions[]', 'core.settings')], access)
    reader = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    post('/login', {'email': reader_email, 'password': 'Package-Reader-2026!'}, '/login', reader)
    assert page('/admin/modules/package', reader)[1] == base + '/'
    assert upload(archive(), reader, '/admin/settings')[1] == base + '/'
    for path in ['/admin/modules/package/install', '/admin/modules/package/cancel']:
        assert post(path, {'token': token, 'trusted': '1'}, '/admin/settings', reader)[1] == base + '/'
    assert token in page('/admin/modules/package')[0]
    post('/admin/modules/package/cancel', {})
    assert 'Package preview' not in page('/admin/modules/package')[0]
    assert 'confirm that you trust' in post('/admin/modules/package/install', {'token': token, 'trusted': '1'})[0]
    preview = upload(archive())[0]
    token = re.search(r'name="token" value="([^"]+)"', preview)[1]
    added = post('/admin/modules/package/install', {'token': token, 'trusted': '1'})[0]
    assert 'Module package_probe added.' in added and 'Package probe' in added and 'Ready to enable' in added
    absent()
    # A second archive cannot overwrite even an inactive module.
    assert 'already exists' in upload(archive({'version': '2.0.0'}))[0]
    post('/admin/modules/package_probe/enable', {}, '/admin/modules')
    assert page('/package-probe')[0] == 'package-probe:preserved-record:1'
    post('/admin/modules/package_probe/disable', {}, '/admin/modules')
    absent()
    post('/admin/modules/package_probe/enable', {}, '/admin/modules')
    assert page('/package-probe')[0] == 'package-probe:preserved-record:1'
    # A malformed provider is rejected on explicit enablement before activation,
    # so it cannot break subsequent requests or run its migration.
    bad = upload(archive({
        'id': 'invalid_probe', 'namespace': 'MCMS\\Modules\\InvalidPackageProbe',
        'provider': 'MCMS\\Modules\\InvalidPackageProbe\\Module', 'public_paths': ['invalid-probe'],
    }, {'Module.php': '<?php namespace MCMS\\Modules\\InvalidPackageProbe; final class Module {}'}))[0]
    bad_token = re.search(r'name="token" value="([^"]+)"', bad)[1]
    post('/admin/modules/package/install', {'token': bad_token, 'trusted': '1'})
    refused = post('/admin/modules/invalid_probe/enable', {}, '/admin/modules')[0]
    assert 'Module provider must implement' in refused
    bad_row = next(row for row in re.findall(r'<tr\b.*?</tr>', refused, re.S) if 'invalid_probe' in row)
    assert 'Ready to enable' in bad_row
    assert page('/package-probe')[0] == 'package-probe:preserved-record:1'
    jar.save(ignore_discard=True, ignore_expires=True)
    print('Package validation, CSRF-protected review/confirmation, access, cancellation, install and scoped migrations passed.')
