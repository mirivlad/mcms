#!/usr/bin/env python3
"""Local HTTP theme update/rollback regression against a disposable installed site."""
import argparse
import html
import http.cookiejar
import re
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', default='http://127.0.0.1:18085')
parser.add_argument('--cookies', required=True)
args = parser.parse_args()
jar = http.cookiejar.MozillaCookieJar(args.cookies)
jar.load(ignore_discard=True, ignore_expires=True)
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
base = args.base_url.rstrip('/')


def get(path):
    return html.unescape(client.open(base + path, timeout=30).read().decode())


def post(path, data, source=None):
    content = get(source or '/admin/themes')
    csrf = html.unescape(re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', content)[1])
    payload = urllib.parse.urlencode({**data, 'csrf_test_name': csrf}).encode()
    return client.open(base + path, payload, timeout=30).read().decode()


def upload(path, filename):
    content = get(path)
    csrf = html.unescape(re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', content)[1])
    boundary = 'mcms-theme-update-local'
    payload = (f'--{boundary}\r\nContent-Disposition: form-data; name="csrf_test_name"\r\n\r\n{csrf}\r\n'
               f'--{boundary}\r\nContent-Disposition: form-data; name="package"; filename="theme.zip"\r\nContent-Type: application/zip\r\n\r\n').encode()
    payload += Path(filename).read_bytes() + f'\r\n--{boundary}--\r\n'.encode()
    return client.open(urllib.request.Request(base + path, payload, {'Content-Type': 'multipart/form-data; boundary=' + boundary}), timeout=30).read().decode()


root = Path(__file__).resolve().parents[2]
if '/admin/themes/mcms-atelier/update' not in get('/admin/themes'):
    page = upload('/admin/themes/package', root / 'examples/packages/mcms-atelier-1.1.1.zip')
    token = re.search(r'name="token" value="([^"]+)"', page)[1]
    post('/admin/themes/package/install', {'token': token, 'trusted': '1'}, '/admin/themes/package')
post('/admin/themes/mcms-atelier/activate', {})
post('/admin/themes/mcms-atelier/home', {'home[title]': 'Retained Atelier home'}, '/admin/themes/mcms-atelier')
post('/admin/themes/mcms-atelier/appearance', {'appearance[accent]': '#123456', 'appearance[phone]': '12345'}, '/admin/themes/mcms-atelier')
path = '/admin/themes/mcms-atelier/update'
page = upload(path, root / 'examples/packages/mcms-atelier-1.1.2.zip')
assert '1.1.1' in page and '1.1.2' in page
# An unconfirmed apply must leave the old theme selected and code intact.
token = re.search(r'name="token" value="([^"]+)"', page)[1]
post(path + '/apply', {'token': token}, path)
assert '/theme-assets/mcms-atelier/1.1.1/' in get('/')
post(path + '/apply', {'token': token, 'trusted': '1'}, path)
assert '/theme-assets/mcms-atelier/1.1.2/' in get('/')
assert 'Retained Atelier home' in get('/') and '12345' in get('/')
for version in ['1.1.1', '1.1.2']:
    response = client.open(base + '/theme-assets/mcms-atelier/' + version + '/css/theme.css')
    assert 'immutable' in response.headers['Cache-Control'] and response.headers['ETag']
    assert response.read()
page = get(path)
source = re.search(r'name="source" value="([^"]+)"', page)[1]
post('/admin/themes/mcms-atelier/rollback', {'source': source, 'trusted': '1'}, path)
assert '/theme-assets/mcms-atelier/1.1.1/' in get('/')
assert 'Retained Atelier home' in get('/') and '12345' in get('/')
try:
    urllib.request.urlopen(base + path)
    # Anonymous users must be redirected to sign-in, never given the update form.
    assert 'name="package"' not in urllib.request.urlopen(base + path).read().decode()
except urllib.error.HTTPError as error:
    assert error.code in (401, 403)
print('Theme update HTTP smoke passed: Atelier update, confirmation, settings, active selection, versioned assets and rollback.')
