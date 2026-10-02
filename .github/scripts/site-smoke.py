#!/usr/bin/env python3
"""Exercise first-party site features through real HTTP forms on an isolated stack."""
import argparse
import html
import http.cookiejar
import json
import re
import struct
import subprocess
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
import zlib

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', default='http://127.0.0.1:8080')
parser.add_argument('--cookies')
args = parser.parse_args()
base = args.base_url.rstrip('/')
jar = http.cookiejar.MozillaCookieJar(args.cookies) if args.cookies else http.cookiejar.CookieJar()
if args.cookies:
    jar.load(ignore_discard=True, ignore_expires=True)
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))


def request(path, data=None, headers=None):
    response = opener.open(urllib.request.Request(base + path, data=data, headers=headers or {}), timeout=30)
    return response.read().decode(), response.url


def token(page, name='csrf_test_name'):
    match = re.search(r'<input\b[^>]*name="' + re.escape(name) + r'"[^>]*value="([^"]+)"', page)
    if not match:
        raise AssertionError('Missing form token: ' + name)
    return html.unescape(match.group(1))


def post(path, fields, form_path=None, form_html=None):
    page = form_html if form_html is not None else request(form_path or path)[0]
    pairs = list(fields.items()) if isinstance(fields, dict) else list(fields)
    pairs.append(('csrf_test_name', token(page)))
    return request(path, urllib.parse.urlencode(pairs).encode())


def module(module_id, action='enable'):
    post('/admin/modules/' + module_id + '/' + action, {}, '/admin/modules')


def expect_404(path):
    try:
        request(path)
        raise AssertionError('Expected 404: ' + path)
    except urllib.error.HTTPError as error:
        assert error.code == 404, (path, error.code)


if not args.cookies:
    post('/install', {'site_name': 'Site smoke', 'locale': 'en', 'email': 'site-smoke@example.test',
                     'username': 'sitesmoke', 'password': 'Local-Site-Smoke-2026!', 'password_confirm': 'Local-Site-Smoke-2026!'}, '/install')
for name in ('pages', 'news', 'menus', 'galleries', 'feedback', 'seo'):
    module(name)

# Real multipart uploads, with a deterministic PNG and no image-library dependency.
def png(color):
    def chunk(kind, value):
        return struct.pack('>I', len(value)) + kind + value + struct.pack('>I', zlib.crc32(kind + value) & 0xffffffff)
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', 960, 480, 8, 2, 0, 0, 0)) + chunk(b'IDAT', zlib.compress((b'\x00' + bytes(color) * 960) * 480)) + chunk(b'IEND', b'')


def upload(name, color):
    page = request('/admin/media')[0]
    boundary = 'mcmsSiteSmokeBoundary'
    parts = []
    for key, value in [('csrf_test_name', token(page)), ('title', name)]:
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{name}.png"\r\nContent-Type: image/png\r\n\r\n'.encode() + png(color) + b'\r\n')
    parts.append(f'--{boundary}--\r\n'.encode())
    request('/admin/media/upload', b''.join(parts), {'Content-Type': 'multipart/form-data; boundary=' + boundary})
    data = json.loads(request('/admin/media/api/files?q=' + urllib.parse.quote(name))[0])
    return next(item for item in data['items'] if item['title'] == name)


first = upload('Site smoke first', [40, 100, 180])
second = upload('Site smoke second', [180, 100, 40])
for image in (first, second):
    assert image['thumbnail_url'].endswith('.thumb.png')
    response = opener.open(base + image['thumbnail_url'])
    assert response.headers.get_content_type() == 'image/png'
    assert struct.unpack('>II', response.read()[16:24]) == (480, 240)

body = '<section><p>Site smoke home content</p><script>alert(1)</script><img src="' + first['url'] + '" onerror="alert(2)"></section>'
page_fields = {'title': 'Site smoke page', 'slug': 'site-smoke-page', 'body': body, 'status': 'published', 'seo_title': '', 'seo_description': ''}
_, page_url = post('/admin/pages/create', page_fields)
page_id = int(re.search(r'/([0-9]+)/edit$', page_url).group(1))
page_fields['slug'] = 'site-smoke-page-renamed'
post('/admin/pages/' + str(page_id) + '/edit', page_fields)
post('/admin/pages/settings', {'default_status': 'draft', 'home_page_id': page_id})
public = request('/')[0]
assert 'Site smoke home content' in public and 'onerror' not in public and 'alert(1)' not in public
module('pages', 'disable')
assert 'Site smoke home content' not in request('/')[0]
module('pages')
assert 'Site smoke home content' in request('/')[0]

news_fields = {'title': 'Site smoke article', 'slug': 'site-smoke-article', 'body': '<p>Article</p>', 'excerpt': '', 'status': 'published', 'published_at': '', 'featured_media_id': first['id'], 'seo_title': '', 'seo_description': ''}
post('/admin/news/create', news_fields)
post('/admin/news/create', {**news_fields, 'title': 'Site smoke scheduled', 'slug': 'site-smoke-scheduled', 'published_at': '2099-01-01T00:00'})
expect_404('/news/site-smoke-scheduled')

album_fields = [('title', 'Site smoke album'), ('slug', 'site-smoke-album'), ('description', 'Smoke album description'), ('status', 'published'), ('cover_media_id', first['id']),
                ('media_ids[]', first['id']), ('media_ids[]', second['id']), ('captions[' + str(first['id']) + ']', 'First caption'), ('captions[' + str(second['id']) + ']', 'Second caption'),
                ('positions[' + str(first['id']) + ']', 10), ('positions[' + str(second['id']) + ']', 0)]
album_form, album_url = post('/admin/galleries/create', album_fields)
album_id = int(re.search(r'/([0-9]+)/edit$', album_url).group(1))
for script in re.findall(r'<script\b[^>]*>(.*?)</script>', album_form, re.S):
    if script.strip():
        subprocess.run(['node', '--check'], input=script, text=True, check=True, capture_output=True)
picker = json.loads(request('/admin/galleries/media?page=1')[0])
assert {first['id'], second['id']} <= {item['id'] for item in picker['items']}
assert all(item['is_image'] for item in picker['items'])
album_public = request('/galleries/site-smoke-album')[0]
assert album_public.index('Second caption') < album_public.index('First caption')
assert 'data-theme="mcms-default"' in album_public
assert 'Site smoke album' in request('/galleries')[0]
resources = json.loads(request('/admin/menus/resources?provider=galleries.album&q=Site%20smoke')[0])
assert 'Site smoke album' in str(resources)
module('galleries', 'disable')
expect_404('/galleries/site-smoke-album')
module('galleries')
assert 'First caption' in request('/galleries/site-smoke-album')[0]

# Submit feedback in a separate anonymous session.
anonymous = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
form = anonymous.open(base + '/contact').read().decode()
response = anonymous.open(base + '/contact', urllib.parse.urlencode({'csrf_test_name': token(form), 'name': 'Site smoke reader', 'email': 'reader@example.test', 'message': 'Site smoke feedback', 'website': ''}).encode())
assert 'Your message has been sent' in response.read().decode()
assert 'Site smoke reader' in request('/admin/feedback')[0]
module('feedback', 'disable')
expect_404('/contact')
module('feedback')
assert 'Site smoke reader' in request('/admin/feedback')[0]

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, message, headers, newurl):
        return None


redirect_opener = urllib.request.build_opener(NoRedirect())
try:
    redirect_opener.open(base + '/site-smoke-page')
    raise AssertionError('Missing old-page redirect')
except urllib.error.HTTPError as error:
    assert error.code == 301, error.code
    assert error.headers['Location'] == base + '/site-smoke-page-renamed'
locations = {element.text for element in ET.fromstring(request('/sitemap.xml')[0]).iter('{http://www.sitemaps.org/schemas/sitemap/0.9}loc')}
assert base + '/site-smoke-page-renamed' in locations
assert base + '/galleries/site-smoke-album' in locations
assert base + '/contact' in locations
assert base + '/site-smoke-page' not in locations
assert base + '/news/site-smoke-scheduled' not in locations

# Used-file deletion must first show all references, including disabled Galleries.
module('galleries', 'disable')
confirmation, _ = post('/admin/media/' + str(first['id']) + '/delete', {}, '/admin/media')
assert 'Delete a file used by content?' in confirmation
assert 'Site smoke album' in confirmation and 'Site smoke article' in confirmation and 'Site smoke page' in confirmation
assert any(item['id'] == first['id'] for item in json.loads(request('/admin/media/api/files')[0])['items'])
post('/admin/media/' + str(first['id']) + '/delete', {'confirm': '1', 'usage_token': token(confirmation, 'usage_token')}, form_html=confirmation)
expect_404(first['url'])
expect_404(first['thumbnail_url'])
module('galleries')
assert 'Image unavailable' in request('/galleries/site-smoke-album')[0]
assert 'First caption' in request('/galleries/site-smoke-album')[0]
assert 'Site smoke album' in request('/admin/galleries')[0]
print('Site HTTP smoke passed: Media previews/confirmation, Galleries, selected Home, Feedback, sitemap and redirects.')
