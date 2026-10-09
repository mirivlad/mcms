#!/usr/bin/env python3
"""Real ZIP update, failed MariaDB DDL and external full-backup recovery."""
import argparse
import hashlib
import html
import http.cookiejar
import io
import json
import re
import subprocess
import time
import threading
import urllib.error
import urllib.parse
import urllib.request
import zipfile

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', default='http://127.0.0.1:8080')
parser.add_argument('--cookies', required=True)
parser.add_argument('--email', default='admin@example.test')
parser.add_argument('--password', default='Correct-Horse-Battery-Staple-42!')
parser.add_argument('--compose-project')
parser.add_argument('--env-file')
args = parser.parse_args()
base = args.base_url.rstrip('/')
jar = http.cookiejar.MozillaCookieJar(args.cookies)
jar.load(ignore_discard=True, ignore_expires=True)
owner = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
form = '/admin/modules/package_probe/update'
compose = ['docker', 'compose']
if args.env_file:
    compose += ['--env-file', args.env_file]
if args.compose_project:
    compose += ['-p', args.compose_project]


def command(*parts, input=None):
    return subprocess.run(compose + list(parts), input=input, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True).stdout


def page(path, client=owner):
    response = client.open(base + path, timeout=45)
    return response.read().decode(), response.url


def post(path, fields, source=form, client=owner):
    token = html.unescape(re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', page(source, client)[0])[1])
    response = client.open(base + path, urllib.parse.urlencode({**fields, 'csrf_test_name': token}).encode(), timeout=45)
    return response.read().decode(), response.url


def upload(content, path=form):
    token = html.unescape(re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', page(path)[0])[1])
    boundary = 'mcms-update-boundary'
    payload = (f'--{boundary}\r\nContent-Disposition: form-data; name="csrf_test_name"\r\n\r\n{token}\r\n'
               f'--{boundary}\r\nContent-Disposition: form-data; name="package"; filename="update.zip"\r\n'
               'Content-Type: application/zip\r\n\r\n').encode() + content + f'\r\n--{boundary}--\r\n'.encode()
    response = owner.open(urllib.request.Request(base + path, payload, {'Content-Type': 'multipart/form-data; boundary=' + boundary}), timeout=45)
    return response.read().decode()


def archive(version, files):
    data = dict(files)
    manifest = json.loads(data['module.json'])
    manifest['version'] = version
    data['module.json'] = json.dumps(manifest).encode()
    output = io.BytesIO()
    with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED) as package:
        for name, content in data.items():
            package.writestr(name, content)
    return output.getvalue()


def receipt():
    return json.loads(command('exec', '-T', 'app', 'cat', '/var/www/html/writable/modules/updates/package_probe/state.json'))


def queued(content):
    source = upload(content)
    token = re.search(r'name="token" value="([^"]+)"', source)[1]
    assert 'confirm that you trust' in post(form + '/queue', {'token': token})[0]
    post(form + '/queue', {'token': token, 'trusted': '1'})
    state = receipt()
    assert state['status'] == 'queued'
    return state


def status(path, code, method=None):
    try:
        owner.open(urllib.request.Request(base + path, data=b'' if method == 'POST' else None), timeout=15)
    except urllib.error.HTTPError as error:
        assert error.code == code, (path, error.code)
        return error.read().decode()
    raise AssertionError(path + ' unexpectedly succeeded')


def sql(query):
    return command('exec', '-T', 'db', 'sh', '-c', 'exec mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" --batch --skip-column-names -e "$1"', 'sh', query).decode().strip()


# An independent enabled module exercises both ordinary shutdown writes and
# the requirement that the parent apply request executes Core only.
probe_output = io.BytesIO()
with zipfile.ZipFile(probe_output, 'w', zipfile.ZIP_DEFLATED) as probe:
    probe.writestr('module.json', json.dumps({
        'id': 'runtime_probe', 'name': 'Runtime probe', 'version': '1.0.0',
        'namespace': 'MCMS\\Modules\\RuntimeProbe', 'provider': 'MCMS\\Modules\\RuntimeProbe\\Module',
        'mcms': '^0.1', 'public_paths': ['runtime-probe'],
    }))
    probe.writestr('Module.php', r'''<?php
namespace MCMS\Modules\RuntimeProbe;
final class Module implements \MCMS\Core\Modules\ModuleProviderInterface {
    public function boot(\MCMS\Core\Modules\ModuleContext $context): void {
        if (! is_cli() && str_ends_with(service('request')->getUri()->getPath(), '/update/apply')) {
            file_put_contents(WRITEPATH . 'unexpected-update-boot', 'yes');
        }
    }
    public function routes(\CodeIgniter\Router\RouteCollection $routes): void {
        $routes->get('runtime-probe', static function (): string {
            register_shutdown_function(static function (): void {
                file_put_contents(WRITEPATH . 'runtime-reader-ready', 'yes');
                usleep(2000000);
                file_put_contents(WRITEPATH . 'runtime-reader-finished', 'yes');
            });
            return 'runtime-probe';
        });
    }
}
''')
probe_preview = upload(probe_output.getvalue(), '/admin/modules/package')
probe_token = re.search(r'name="token" value="([^"]+)"', probe_preview)[1]
post('/admin/modules/package/install', {'token': probe_token, 'trusted': '1'}, '/admin/modules/package')
post('/admin/modules/runtime_probe/enable', {}, '/admin/modules')

assert page('/package-probe')[0] == 'package-probe:preserved-record:1'  # warms FPM opcache too
post('/admin/modules/package_probe/disable', {}, '/admin/modules')
# Preview/queue are isolated from existing code and registry.
installed = command('exec', '-T', 'app', 'cat', '/var/www/html/writable/modules/installed/PackageProbe/module.json')
files = {'module.json': installed}
for path in ['Module.php', 'Controllers/Probe.php', 'Database/Migrations/2026-10-09-000001_CreateProbe.php']:
    files[path] = command('exec', '-T', 'app', 'cat', '/var/www/html/writable/modules/installed/PackageProbe/' + path)
files['Controllers/Probe.php'] = files['Controllers/Probe.php'].replace(b"count($rows);", b"count($rows) . ':' . $rows[0]['stage'];")
files['Database/Migrations/2026-10-09-000002_UpdateProbe.php'] = b'''<?php
namespace MCMS\\Modules\\PackageProbe\\Database\\Migrations;
final class UpdateProbe extends \\CodeIgniter\\Database\\Migration {
    public function up(): void {
        $this->forge->addColumn('package_probe_records', ['stage' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'updated']]);
    }
    public function down(): void { throw new \\RuntimeException('Down migrations must never run'); }
}
'''
content = archive('1.1.0', files)
reader_name = 'update.reader.' + str(int(time.time()))
reader_email = reader_name + '@example.test'
users = post('/admin/users/create', {'username': reader_name, 'email': reader_email, 'password': 'Update-Reader-2026!'}, '/admin/users/create')[0]
row = next(row for row in re.findall(r'<tr\b.*?</tr>', users, re.S) if reader_name in row)
user_id = re.search(r'/admin/users/(\d+)/access', row)[1]
access = '/admin/users/' + user_id + '/access'
# Core administration alone is insufficient for the module update workflow.
source = page(access)[0]
csrf_value = html.unescape(re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', source)[1])
owner.open(base + access, urllib.parse.urlencode([('csrf_test_name', csrf_value), ('permissions[]', 'core.admin'), ('permissions[]', 'core.settings')]).encode()).read()
reader = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
post('/login', {'email': reader_email, 'password': 'Update-Reader-2026!'}, '/login', reader)
assert page(form, reader)[1] == base + '/'
assert page(form + '/snapshot', reader)[1] == base + '/'
reader_token = html.unescape(re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', page('/admin/settings', reader)[0])[1])
for action in ['', '/queue', '/cancel', '/apply', '/restore']:
    response = reader.open(base + form + action, urllib.parse.urlencode({'csrf_test_name': reader_token, 'operation': 'wrong', 'backup': '1', 'database_restored': '1', 'trusted': '1'}).encode())
    assert response.url == base + '/'
    # CSRF tokens rotate: fetch the new token from an accessible Core form.
    reader_token = html.unescape(re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', page('/admin/settings', reader)[0])[1])

state = queued(content)
assert command('exec', '-T', 'app', 'cat', '/var/www/html/writable/modules/installed/PackageProbe/module.json') == installed
assert sql('SELECT version FROM mcms_modules WHERE module_id="package_probe"') == '1.0.0'
snapshot = owner.open(base + form + '/snapshot').read()
assert hashlib.sha256(snapshot).hexdigest() == state['previous_sha256']
assert json.loads(zipfile.ZipFile(io.BytesIO(snapshot)).read('module.json'))['version'] == '1.0.0'
post('/admin/modules/package_probe/enable', {}, '/admin/modules')
assert sql('SELECT enabled FROM mcms_modules WHERE module_id="package_probe"') == '0'
assert 'Confirm the database backup' in post(form + '/apply', {'operation': state['operation']})[0]
post(form + '/cancel', {'operation': state['operation']})
assert command('exec', '-T', 'app', 'cat', '/var/www/html/writable/modules/installed/PackageProbe/module.json') == installed
state = queued(content)
backup_before_success = command('exec', '-T', 'db', 'sh', '-c', 'exec mariadb-dump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" --single-transaction "$MARIADB_DATABASE"')
assert b'package_probe_records' in backup_before_success
reader_result = []
def runtime_reader():
    reader_result.append(urllib.request.urlopen(base + '/runtime-probe', timeout=30).read().decode())

reader_thread = threading.Thread(target=runtime_reader)
reader_thread.start()
for _ in range(30):
    ready = subprocess.run(compose + ['exec', '-T', 'app', 'test', '-f', '/var/www/html/writable/runtime-reader-ready'], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    if ready.returncode == 0:
        break
    time.sleep(0.05)
else:
    raise AssertionError('Runtime reader did not enter shutdown')
assert 'Update applied' in post(form + '/apply', {'operation': state['operation'], 'backup': '1'})[0]
# Apply must wait for the late shutdown write, and must not boot enabled modules
# in the Core parent request even though normal administration requests boot them.
command('exec', '-T', 'app', 'test', '-f', '/var/www/html/writable/runtime-reader-finished')
command('exec', '-T', 'app', 'test', '!', '-f', '/var/www/html/writable/unexpected-update-boot')
reader_thread.join(timeout=10)
assert reader_result == ['runtime-probe']
assert receipt()['status'] == 'applied'
assert sql('SELECT CONCAT(version, ":", enabled) FROM mcms_modules WHERE module_id="package_probe"') == '1.1.0:0'
post('/admin/modules/package_probe/enable', {}, '/admin/modules')
assert page('/package-probe')[0] == 'package-probe:preserved-record:1:updated'
post('/admin/modules/package_probe/disable', {}, '/admin/modules')
files['Database/Migrations/2026-10-09-000003_FailProbe.php'] = b'''<?php
namespace MCMS\\Modules\\PackageProbe\\Database\\Migrations;
final class FailProbe extends \\CodeIgniter\\Database\\Migration {
    public function up(): void {
        $this->forge->addColumn('package_probe_records', ['partial' => ['type' => 'INT', 'default' => 1]]);
        throw new \\RuntimeException('Deliberate failure after MariaDB DDL');
    }
    public function down(): void { throw new \\RuntimeException('Down migrations must never run'); }
}
'''
state = queued(archive('1.2.0', files))
backup = command('exec', '-T', 'db', 'sh', '-c', 'exec mariadb-dump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" --single-transaction "$MARIADB_DATABASE"')
assert b'package_probe_records' in backup
result = post(form + '/apply', {'operation': state['operation'], 'backup': '1'})[0]
assert 'Deliberate failure after MariaDB DDL' in result
assert receipt()['status'] == 'failed'
assert sql('SHOW COLUMNS FROM package_probe_records LIKE "partial"')
assert 'Site maintenance' in status('/', 503)
status('/package-probe', 503)
status('/admin/modules/package_probe/enable', 503, 'POST')
status('/admin/settings', 503)
assert 'Open module recovery' in page('/admin/modules')[0]
assert 'Restore previous code' in page(form)[0]
# The startup updater must not run migrations or prevent FPM startup during recovery.
assert 'startup migrations skipped' in command('exec', '-T', 'app', 'php', 'spark', 'mcms:update').decode()
command('up', '-d', '--force-recreate', 'app', 'web')
for _ in range(30):
    try:
        urllib.request.urlopen(base + '/_health', timeout=5)
        break
    except (urllib.error.URLError, TimeoutError, ConnectionError):
        time.sleep(1)
else:
    raise AssertionError('Recovery runtime did not restart')
# Re-login with a new session after app recreation; normal landing pages remain blocked.
jar.clear()
try:
    post('/login', {'email': args.email, 'password': args.password}, '/login')
except urllib.error.HTTPError as error:
    assert error.code == 503
assert 'Restore previous code' in page(form)[0]
# Claiming DB recovery without the checkbox is refused. A matching migration history alone
# does not prove full data recovery, hence the explicit operator confirmation in the product.
assert 'Confirm the database backup' in post(form + '/restore', {'operation': state['operation']})[0]
command('exec', '-T', 'db', 'sh', '-c', 'exec mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"', input=backup)
assert not sql('SHOW COLUMNS FROM package_probe_records LIKE "partial"')
assert 'Previous code restored' in post(form + '/restore', {'operation': state['operation'], 'database_restored': '1'})[0]
assert receipt()['status'] == 'restored'
post('/admin/modules/package_probe/enable', {}, '/admin/modules')
assert page('/package-probe')[0] == 'package-probe:preserved-record:1:updated'
jar.save(ignore_discard=True, ignore_expires=True)
print('ZIP update, scoped migrations, preserved records, PHP cache replacement, cancellation, persistent maintenance, application recreation and full-database/code recovery passed.')
