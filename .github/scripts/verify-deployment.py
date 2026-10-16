#!/usr/bin/env python3
"""Build, recreate and restore disposable mCMS stacks without GitHub Actions."""
import argparse
import base64
from contextlib import contextmanager
from datetime import datetime, timezone
import hashlib
import html
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import shlex
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
SCRIPTS = ROOT / '.github/scripts'
PASSWORD = 'Correct-Horse-Battery-Staple-42!'
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--report-dir', type=Path, help='New directory for JSON evidence, logs and the test backup')
args = parser.parse_args()
if not __debug__:
    parser.error('Python assertions must be enabled for the smoke scenarios.')
if args.report_dir:
    directory = args.report_dir.resolve()
    directory.mkdir(parents=True, mode=0o700)
else:
    directory = Path(tempfile.mkdtemp(prefix='mcms-deployment-check-'))
print('Evidence: ' + str(directory), flush=True)
suffix = secrets.token_hex(6)
images = ['mcms-check-' + suffix + ':app', 'mcms-check-' + suffix + ':web']
projects = ['mcms-check-' + suffix + '-source', 'mcms-check-' + suffix + '-restore']
env_file = directory / 'compose.env'
environment = {k: v for k, v in os.environ.items() if not k.startswith(('MCMS_', 'COMPOSE_'))}
environment.update({
    'MCMS_DB_PASSWORD': secrets.token_hex(20), 'MCMS_DB_ROOT_PASSWORD': secrets.token_hex(20),
    'MCMS_HTTP_PORT': '0', 'MCMS_APP_IMAGE': images[0], 'MCMS_WEB_IMAGE': images[1],
    'MCMS_DEMO_SEED': '0', 'MCMS_BASE_URL': '', 'MCMS_COOKIE_SECURE': '0',
    'COMPOSE_FILE': str(ROOT / 'compose.yaml'), 'COMPOSE_ENV_FILES': str(env_file),
})
env_file.write_text('\n'.join(k + '=' + v for k, v in environment.items() if k.startswith('MCMS_')) + '\n')
env_file.chmod(0o600)
report = {'started_at': datetime.now(timezone.utc).isoformat(), 'status': 'running',
          'projects': projects, 'steps': [], 'scope': 'Local Docker rebuild/recreation and exact-version ZIP recovery; no production or previous-release upgrade.'}
started = []


def save_report():
    (directory / 'report.json').write_text(json.dumps(report, indent=2, ensure_ascii=False) + '\n')


def run(command, *, project=None, input=None, binary=False, timeout=180, check=True):
    env = {**environment, **({'COMPOSE_PROJECT_NAME': project} if project else {})}
    result = subprocess.run(command, cwd=ROOT, env=env, input=input,
                            capture_output=True, text=not binary, timeout=timeout)
    with (directory / 'commands.log').open('a') as log:
        log.write('$ ' + shlex.join(map(str, command)) + '\n')
        for output in [result.stdout, result.stderr]:
            log.write(('[binary output: ' + str(len(output)) + ' bytes]\n') if isinstance(output, bytes) else output)
        log.write('\nExit: ' + str(result.returncode) + '\n')
    if check and result.returncode:
        raise RuntimeError('Command failed (' + str(result.returncode) + '): ' + shlex.join(map(str, command)))
    return result.stdout


def compose(project, *command, **kwargs):
    return run(['docker', 'compose', '--project-directory', str(ROOT), '--env-file', str(env_file),
                '-f', str(ROOT / 'compose.yaml'), '-p', project, *command], project=project, **kwargs)


@contextmanager
def step(label):
    print(label + '...', flush=True)
    current = {'name': label, 'status': 'running'}
    report['steps'].append(current)
    save_report()
    before = time.monotonic()
    try:
        yield
        current['status'] = 'passed'
    except BaseException as error:
        current.update(status='failed', error=type(error).__name__ + ': ' + str(error))
        raise
    finally:
        current['seconds'] = round(time.monotonic() - before, 2)
        save_report()


def sql(project, statement):
    return compose(project, 'exec', '-T', 'db', 'sh', '-c',
                   'exec mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" --batch --skip-column-names',
                   input=statement).strip()


def wait_ready(project):
    address = compose(project, 'port', 'web', '80').strip()
    assert address.startswith('127.0.0.1:'), address
    base = 'http://' + address
    deadline = time.monotonic() + 180
    while time.monotonic() < deadline:
        try:
            with urllib.request.urlopen(base + '/_health', timeout=3) as response:
                if json.load(response) == {'status': 'ok', 'database': 'ok'}:
                    return base
        except (OSError, urllib.error.URLError, ValueError):
            pass
        time.sleep(1)
    raise RuntimeError('HTTP/database health did not become ready: ' + project)


class Site:
    def __init__(self, base, name):
        self.base = base
        self.jar = http.cookiejar.MozillaCookieJar(str(directory / (name + '.cookies')))
        self.client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))

    def get(self, path):
        with self.client.open(self.base + path, timeout=40) as response:
            return html.unescape(response.read().decode()), response.url

    def post(self, path, fields, form=None):
        source = self.get(form or path)[0]
        csrf = re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', source)[1]
        payload = urllib.parse.urlencode({**fields, 'csrf_test_name': csrf}).encode()
        with self.client.open(self.base + path, payload, timeout=60) as response:
            return html.unescape(response.read().decode()), response.url

    def upload(self, path, field, name, content, mime, form=None):
        source = self.get(form or path)[0]
        csrf = re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', source)[1]
        boundary = 'mcms-deployment-' + suffix
        body = (f'--{boundary}\r\nContent-Disposition: form-data; name="csrf_test_name"\r\n\r\n{csrf}\r\n'
                f'--{boundary}\r\nContent-Disposition: form-data; name="{field}"; filename="{name}"\r\n'
                f'Content-Type: {mime}\r\n\r\n').encode() + content + f'\r\n--{boundary}--\r\n'.encode()
        request = urllib.request.Request(self.base + path, body, {'Content-Type': 'multipart/form-data; boundary=' + boundary})
        with self.client.open(request, timeout=60) as response:
            return html.unescape(response.read().decode()), response.url

    def install(self, name):
        assert 'Initial setup' in self.get('/install')[0]
        _, destination = self.post('/install', {'site_name': name, 'locale': 'en',
            'email': 'admin@example.test', 'username': 'admin', 'password': PASSWORD, 'password_confirm': PASSWORD})
        assert destination == self.base + '/admin', destination
        self.jar.save(ignore_discard=True, ignore_expires=True)

    def login(self):
        self.jar.clear()
        self.post('/login', {'email': 'admin@example.test', 'password': PASSWORD})
        content, destination = self.get('/admin')
        assert destination == self.base + '/admin' and 'class="admin-navigation' in content, 'Administrator login failed'
        self.jar.save(ignore_discard=True, ignore_expires=True)


def smoke(name, project, site, *extra):
    run([sys.executable, str(SCRIPTS / name), '--base-url', site.base,
         *extra], project=project, timeout=420)


def snapshot(project):
    # The updater refreshes registry/settings timestamps on every run. Compare
    # identities and values, while retaining complete domain rows.
    queries = {table: 'SELECT * FROM `' + table + '` ORDER BY 1;' for table in [
        'pages', 'news_articles', 'catalogue_products', 'catalogue_variants', 'catalogue_categories',
        'product_image_sets', 'media_files', 'settings', 'package_probe_records']}
    queries['mcms_modules'] = 'SELECT module_id, version, enabled FROM mcms_modules ORDER BY module_id;'
    queries['settings'] = 'SELECT id, class, `key`, value, type, context FROM settings ORDER BY id;'
    rows = {table: hashlib.sha256(sql(project, query).encode()).hexdigest() for table, query in queries.items()}
    php = '''$result = [];
foreach (['modules-data', 'themes-data'] as $root) {
    $base = 'writable/' . $root . '/';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->isFile() && !str_ends_with($file->getFilename(), '.lock')) {
            $result[$root . '/' . substr($file->getPathname(), strlen($base))] = hash_file('sha256', $file->getPathname());
        }
    }
}
ksort($result); echo json_encode($result);'''
    files = json.loads(compose(project, 'exec', '-T', 'app', 'php', '-r', php))
    schema = sql(project, 'SELECT table_name, column_name, column_type, is_nullable, column_default, column_key, extra '
                 'FROM information_schema.columns WHERE table_schema=DATABASE() ORDER BY table_name, ordinal_position;')
    migrations = sql(project, 'SELECT namespace, version, class FROM migrations ORDER BY namespace, version, class;')
    return {'tables': rows, 'files': files, 'schema_sha256': hashlib.sha256(schema.encode()).hexdigest(),
            'migrations_sha256': hashlib.sha256(migrations.encode()).hexdigest()}


def compare_snapshot(project, expected, label):
    actual = snapshot(project)
    (directory / (label + '-snapshot.json')).write_text(json.dumps(actual, indent=2) + '\n')
    changed = [section + ':' + key for section in ['tables', 'files']
               for key in expected[section].keys() | actual[section].keys()
               if expected[section].get(key) != actual[section].get(key)]
    changed += [key for key in ['schema_sha256', 'migrations_sha256'] if expected[key] != actual[key]]
    assert actual == expected, label + ' changed: ' + ', '.join(changed)


def check_public(site, media_path, image):
    for path, expected in [('/', 'Saved studio title'), ('/demo-about', 'О проекте'),
                           ('/news/demo-opening', 'Добро пожаловать'), ('/shop/demo-ceramic-mug', 'Керамическая кружка'),
                           ('/package-probe', 'package-probe:preserved-record:1')]:
        assert expected in site.get(path)[0], path
    with urllib.request.urlopen(site.base + media_path, timeout=20) as response:
        assert response.read() == image, 'Uploaded image changed'
    with urllib.request.urlopen(site.base + '/theme-assets/theme-probe/1.0.0/css/style.css', timeout=20) as response:
        assert b'.eyebrow' in response.read(), 'Installed theme assets missing'
    assert 'class="admin-navigation' in site.get('/admin')[0], 'Restored admin login failed'


def verify():
    source, target = projects
    with step('Check local Docker and storage contracts'):
        endpoint = environment.get('DOCKER_HOST') if not environment.get('DOCKER_CONTEXT') else None
        endpoint = endpoint or run(['docker', 'context', 'inspect', '--format', '{{.Endpoints.docker.Host}}']).strip()
        if not endpoint.startswith(('unix://', 'npipe://')):
            raise RuntimeError('This verification command requires a local Docker daemon.')
        report['git_commit'] = run(['git', 'rev-parse', 'HEAD']).strip()
        report['working_tree'] = run(['git', 'status', '--porcelain']).splitlines()
        run(['docker', 'version', '--format', '{{.Server.Version}}'])
        for model in ['compose.yaml', 'portainer-stack.yml']:
            config = run(['docker', 'compose', '--env-file', str(env_file), '-f', str(ROOT / model),
                          'config', '--format', 'json'], binary=True)
            # Do not write resolved configuration (database credentials) to logs.
            run([sys.executable, str(SCRIPTS / 'storage-contract-check.py')], input=config, binary=True)
    with step('Build and install isolated source stack'):
        started.append(source)
        compose(source, 'up', '-d', '--build', 'db', 'app', 'web', timeout=900)
        source_site = Site(wait_ready(source), 'source')
        source_site.install('Deployment verification')
        report['core_version'] = compose(source, 'exec', '-T', 'app', 'php', '-r',
            "require 'src/Core/Version.php'; echo MCMS\\Core\\Version::VERSION;").strip()
    with step('Populate demo content, Media and optional module schemas'):
        smoke('demo-smoke.py', source, source_site, '--compose-project', source)
        for module in ['media', 'menus', 'comments', 'ratings', 'captcha', 'notifications']:
            source_site.post('/admin/modules/' + module + '/enable', {}, '/admin/modules')
            assert sql(source, "SELECT enabled FROM mcms_modules WHERE module_id='" + module + "';") == '1', module
        image = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl6AAAAAASUVORK5CYII=')
        source_site.upload('/admin/media/upload', 'file', 'verification.png', image, 'image/png', '/admin/media')
        media_path = re.search(r'/assets/uploads/media/[0-9]{4}/[0-9]{2}/[a-f0-9]{32}\.png', source_site.get('/admin/media')[0])[0]
    with step('Install and exercise external module and theme packages'):
        cookies = str(source_site.jar.filename)
        smoke('module-packages-smoke.py', source, source_site, '--cookies', cookies)
        smoke('theme-packages-smoke.py', source, source_site, '--cookies', cookies)
        source_site.login()
        check_public(source_site, media_path, image)
    with step('Verify backup mutation recovery and archive integrity'):
        archive = directory / 'site-backup.zip'
        smoke('backup-restore-smoke.py', source, source_site, '--compose-project', source, '--archive-output', str(archive))
        run([sys.executable, str(SCRIPTS / 'backup-archive-check.py'), str(archive)])
        source_site.login()
        check_public(source_site, media_path, image)
        expected = snapshot(source)
        (directory / 'snapshot.json').write_text(json.dumps(expected, indent=2) + '\n')
        report['archive_sha256'] = hashlib.sha256(archive.read_bytes()).hexdigest()
        report['compared_tables'] = list(expected['tables'])
        report['compared_files'] = len(expected['files'])
    with step('Recreate app/web and verify persisted content, packages and settings'):
        previous = {service: compose(source, 'ps', '-q', service).strip() for service in ['app', 'web']}
        compose(source, 'up', '-d', '--no-build', '--force-recreate', 'app', 'web')
        source_site.base = wait_ready(source)  # Docker allocates a new ephemeral port.
        source_site.login()
        for service, old_id in previous.items():
            assert old_id and old_id != compose(source, 'ps', '-q', service).strip(), service
        check_public(source_site, media_path, image)
        compare_snapshot(source, expected, 'recreated')
        compose(source, 'exec', '-T', 'app', 'php', 'spark', 'mcms:update')
        compare_snapshot(source, expected, 'updated')
    with step('Install independent recovery stack with empty data volumes'):
        started.append(target)
        compose(target, 'up', '-d', '--no-build', 'db', 'app', 'web')
        target_site = Site(wait_ready(target), 'restore')
        target_site.install('Empty recovery target')
        assert sql(target, "SHOW TABLES LIKE 'pages';") == '', 'Recovery target is not empty'
    with step('Upload saved ZIP and restore into the independent stack'):
        preview, url = target_site.upload('/admin/backups/restore/upload', 'archive', 'site-backup.zip',
                                          archive.read_bytes(), 'application/zip', '/admin/backups')
        assert url.endswith('/admin/backups/restore/confirm'), url
        token = re.search(r'name="token" value="([a-f0-9]{32})"', preview)[1]
        try:
            target_site.post('/admin/backups/restore/apply', {'token': token, 'acknowledge': '1'}, '/admin/backups/restore/confirm')
        except urllib.error.HTTPError as error:
            assert error.code == 503, error
        deadline = time.monotonic() + 180
        while time.monotonic() < deadline:
            state = json.loads(compose(target, 'exec', '-T', 'app', 'php', '-r',
                'echo file_get_contents("writable/backups/restore/' + token + '.json");'))
            if state['status'] in ['completed', 'failed', 'recovery_required']:
                break
            time.sleep(1)
        assert state['status'] == 'completed', state
        report['restore_status'] = state['status']
        wait_ready(target)
        target_site.login()
        check_public(target_site, media_path, image)
        compare_snapshot(target, expected, 'restored')
        compose(target, 'exec', '-T', 'app', 'test', '!', '-e', 'writable/backups/restore-active.json')
    with step('Check restored site after another app/web recreation'):
        compose(target, 'up', '-d', '--no-build', '--force-recreate', 'app', 'web')
        target_site.base = wait_ready(target)
        target_site.login()
        check_public(target_site, media_path, image)
        compare_snapshot(target, expected, 'restored-recreated')


save_report()
try:
    verify()
    report['status'] = 'passed'
except (Exception, KeyboardInterrupt) as error:
    report.update(status='failed', error=type(error).__name__ + ': ' + str(error))
    print('FAILED: ' + report['error'], file=sys.stderr, flush=True)
finally:
    # Every resource below belongs to this invocation's random project names.
    # Capture diagnostics before deleting the isolated stacks, including failures.
    cleanup_errors = []
    for project in reversed(started):
        try:
            compose(project, 'logs', '--no-color', timeout=40, check=False)
            compose(project, 'down', '--volumes', '--remove-orphans', timeout=120)
        except Exception as error:
            cleanup_errors.append(str(error))
    for image in images:
        try:
            existing = run(['docker', 'image', 'ls', '-q', image], timeout=20).strip()
            if existing:
                run(['docker', 'image', 'rm', image], timeout=30)
        except Exception as error:
            cleanup_errors.append(str(error))
    report['cleanup'] = {'status': 'failed' if cleanup_errors else 'passed', 'errors': cleanup_errors}
    if cleanup_errors:
        report['status'] = 'failed'
    else:
        env_file.unlink(missing_ok=True)
        for cookie in directory.glob('*.cookies'):
            cookie.unlink()
    report['finished_at'] = datetime.now(timezone.utc).isoformat()
    save_report()
print(report['status'].upper() + ': ' + str(directory / 'report.json'), flush=True)
sys.exit(0 if report['status'] == 'passed' else 1)
