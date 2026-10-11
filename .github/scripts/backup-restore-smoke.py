#!/usr/bin/env python3
"""Restore a mutated clean-install site from an archive using real admin endpoints."""
import hashlib
import html
import http.cookiejar
import io
import json
import re
import subprocess
import time
import zipfile
import urllib.parse
import urllib.request
import urllib.error

BASE = 'http://127.0.0.1:8080'
jar = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

def exec_app(*args):
    return subprocess.check_output(['docker', 'compose', 'exec', '-T', 'app', *args], text=True)

def query(statement):
    return subprocess.check_output([
        'docker', 'compose', 'exec', '-T', 'db', 'mariadb',
        '-umcms', '-pci-test', 'mcms', '--batch', '--skip-column-names', '-e', statement
    ], text=True).strip()

def page(path):
    reply = client.open(BASE + path, timeout=25)
    return html.unescape(reply.read().decode()), reply.url

def post(path, fields, from_page=None):
    source = page(from_page or path)[0]
    csrf = re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', source)[1]
    data = dict(fields)
    data['csrf_test_name'] = csrf
    reply = client.open(BASE + path, urllib.parse.urlencode(data).encode(), timeout=40)
    return html.unescape(reply.read().decode()), reply.url

page('/login')
_, where = post('/login', {
    'email': 'admin@example.test', 'password': 'Correct-Horse-Battery-Staple-42!'
})
assert '/admin' in where, where
original = query("SELECT title FROM pages WHERE slug='demo-about';")
assert original, 'Demo content missing'

# Keep a non-empty filesystem marker and an SQL row from a known snapshot.
exec_app('sh', '-c', "mkdir -p writable/modules-data/data/restore-smoke && printf original > writable/modules-data/data/restore-smoke/marker.txt")
line = exec_app('php', 'spark', 'mcms:backup:create')
name = re.search(r'(mcms-[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}.zip)', line)[1]
query("UPDATE pages SET title='MUTATED AFTER BACKUP' WHERE slug='demo-about';")
exec_app('sh', '-c', "printf mutated > writable/modules-data/data/restore-smoke/marker.txt")

_, url = post('/admin/backups/' + name + '/restore', {}, '/admin/backups')
assert url.endswith('/admin/backups/restore/confirm'), url
source = page('/admin/backups/restore/confirm')[0]
assert 'Type RESTORE' in source
token = re.search(r'name="token" value="([a-f0-9]{32})"', source)[1]

# Authenticated browser starts a separate CLI worker; returns before mutation.
try:
    post('/admin/backups/restore/apply', {
        'token': token, 'confirmation': 'RESTORE', 'acknowledge': '1'
    }, '/admin/backups/restore/confirm')
except urllib.error.HTTPError as error:
    # The background worker activates a 503 maintenance response before the
    # browser finishes following its redirect. This is expected.
    assert error.code == 503, error

for _ in range(90):
    state = json.loads(exec_app('php', '-r',
        'echo file_get_contents("writable/backups/restore/' + token + '.json");'))
    if state['status'] in ('completed', 'failed', 'recovery_required'):
        break
    time.sleep(2)
assert state['status'] == 'completed', state
assert query("SELECT title FROM pages WHERE slug='demo-about';") == original
assert exec_app('cat', 'writable/modules-data/data/restore-smoke/marker.txt') == 'original'
assert state['safety'].endswith('.zip')
exec_app('test', '-s', 'writable/backups/' + state['safety'])
exec_app('test', '!', '-e', 'writable/backups/restore-active.json')

# Test uploading a downloaded backup, without applying it twice.
backup_bytes = subprocess.check_output(['docker', 'compose', 'exec', '-T', 'app',
    'cat', 'writable/backups/' + name])
with open('/tmp/mcms-restore-upload.zip', 'wb') as stream:
    stream.write(backup_bytes)
assert hashlib.sha256(backup_bytes).hexdigest() != hashlib.sha256(b'').hexdigest()

# Restored auth sessions may differ: sign in again with the saved credentials.
jar.clear()
page('/login')
post('/login', {'email': 'admin@example.test', 'password': 'Correct-Horse-Battery-Staple-42!'})
source = page('/admin/backups')[0]
assert 'Restore site' in source
csrf = re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', source)[1]
boundary = 'mcms-restore-smoke-boundary'
form = (b'--' + boundary.encode() + b'\r\n'
        + b'Content-Disposition: form-data; name="csrf_test_name"\r\n\r\n'
        + csrf.encode() + b'\r\n'
        + b'--' + boundary.encode() + b'\r\n'
        + b'Content-Disposition: form-data; name="archive"; filename="restore.zip"\r\n'
        + b'Content-Type: application/zip\r\n\r\n'
        + backup_bytes + b'\r\n--' + boundary.encode() + b'--\r\n')
request = urllib.request.Request(BASE + '/admin/backups/restore/upload', form, {
    'Content-Type': 'multipart/form-data; boundary=' + boundary,
})
reply = client.open(request, timeout=40)
assert reply.url.endswith('/admin/backups/restore/confirm'), reply.url
assert 'Uploaded ZIP' in reply.read().decode()

# Forge a well-formed backup from another mCMS version (all manifest hashes
# corrected) and verify the admin preview cannot offer a restore button.
with zipfile.ZipFile(io.BytesIO(backup_bytes)) as original_zip:
    files = {item: original_zip.read(item) for item in original_zip.namelist()}
metadata = json.loads(files['app-version.json'])
installed_version = metadata['version']
assert installed_version != '9.9.9'
metadata['version'] = '9.9.9'
files['app-version.json'] = (json.dumps(metadata) + '\n').encode()
manifest = json.loads(files['manifest.json'])
manifest['files']['app-version.json'] = {
    'size': len(files['app-version.json']),
    'sha256': hashlib.sha256(files['app-version.json']).hexdigest(),
}
files['manifest.json'] = json.dumps(manifest).encode()
buffer = io.BytesIO()
with zipfile.ZipFile(buffer, 'w', zipfile.ZIP_DEFLATED) as changed_zip:
    for path, content in files.items():
        changed_zip.writestr(path, content)

source = page('/admin/backups')[0]
csrf = re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', source)[1]
form = (b'--' + boundary.encode() + b'\r\n'
        + b'Content-Disposition: form-data; name="csrf_test_name"\r\n\r\n'
        + csrf.encode() + b'\r\n'
        + b'--' + boundary.encode() + b'\r\n'
        + b'Content-Disposition: form-data; name="archive"; filename="old-version.zip"\r\n'
        + b'Content-Type: application/zip\r\n\r\n'
        + buffer.getvalue() + b'\r\n--' + boundary.encode() + b'--\r\n')
request = urllib.request.Request(BASE + '/admin/backups/restore/upload', form, {
    'Content-Type': 'multipart/form-data; boundary=' + boundary,
})
reply = client.open(request, timeout=40)
assert reply.url.endswith('/admin/backups/restore/confirm'), reply.url
preview = html.unescape(reply.read().decode())
assert '9.9.9' in preview and installed_version in preview
assert 'requires mCMS 9.9.9' in preview, preview[-1000:]
assert 'name="confirmation"' not in preview
exec_app('test', '!', '-e', 'writable/backups/restore-active.json')
print('Restore smoke passed: SQL and files, safety copy, upload, version mismatch blocked.')
