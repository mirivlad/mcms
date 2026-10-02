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

# Group management uses one editor dialog and a separate deletion confirmation.
access_page = request('/admin/access')[0]
assert '<table' in access_page and 'id="access-create-group"' in access_page
assert 'id="access-group-dialog"' in access_page and 'id="access-delete-dialog"' in access_page
for script in re.findall(r'<script\b[^>]*>(.*?)</script>', access_page, re.S):
    if script.strip():
        subprocess.run(['node', '--check'], input=script, text=True, check=True, capture_output=True)
group_fields = [('mode', 'create'), ('id', 'smoke_editors'), ('title', 'Smoke editors'),
                ('description', 'Content team'), ('permissions[]', 'core.admin'), ('permissions[]', 'pages.edit')]
created, _ = post('/admin/access/group', group_fields, '/admin/access')
assert 'Access group saved.' in created and 'data-edit-group="smoke_editors"' in created
duplicate, _ = post('/admin/access/group', {**dict(group_fields), 'title': 'Duplicate attempt'}, '/admin/access')
assert 'A group with this ID already exists.' in duplicate
assert '<strong>Smoke editors</strong>' in duplicate and '<strong>Duplicate attempt</strong>' not in duplicate
assert '"mode":"create"' in duplicate and '"title":"Duplicate attempt"' in duplicate
invalid, _ = post('/admin/access/group', {'mode': 'edit', 'id': 'smoke_editors', 'title': '',
                                        'description': 'Keep input', 'permissions[]': 'core.admin'}, '/admin/access')
assert 'Group title is required.' in invalid and '"mode":"edit"' in invalid and 'Keep input' in invalid
edited, _ = post('/admin/access/group', {'mode': 'edit', 'id': 'smoke_editors', 'title': 'Smoke writers',
                                       'description': 'Updated team', 'permissions[]': 'core.admin'}, '/admin/access')
assert '<strong>Smoke writers</strong>' in edited and 'Updated team' in edited
unconfirmed, _ = post('/admin/access/group/delete', {'id': 'smoke_editors'}, '/admin/access')
assert 'Confirm group deletion.' in unconfirmed and 'data-edit-group="smoke_editors"' in unconfirmed
for group in ('superadmin', 'admin', 'user'):
    protected, _ = post('/admin/access/group/delete', {'id': group, 'confirm': '1'}, '/admin/access')
    assert 'System groups and the default registration group cannot be deleted.' in protected
    assert re.search(r'data-delete-group="' + group + r'"[^>]*disabled', protected)
deleted, _ = post('/admin/access/group/delete', {'id': 'smoke_editors', 'confirm': '1'}, '/admin/access')
assert 'Access group deleted.' in deleted and 'data-edit-group="smoke_editors"' not in deleted
stale, _ = post('/admin/access/group', {'mode': 'edit', 'id': 'smoke_editors', 'title': 'Stale editor'}, '/admin/access')
assert 'Unknown group: smoke_editors' in stale and 'data-edit-group="smoke_editors"' not in stale

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
_, news_url = post('/admin/news/create', news_fields)
news_id = int(re.search(r'/([0-9]+)/edit$', news_url).group(1))
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
shortcode = '[gallery id="' + str(album_id) + '"]'
gallery_picker = json.loads(request('/admin/galleries/picker?q=Site%20smoke%20album')[0])
assert gallery_picker['items'] == [{'id': album_id, 'title': 'Site smoke album', 'thumbnail_url': first['thumbnail_url']}]
for path in ('/admin/pages/' + str(page_id) + '/edit', '/admin/news/' + str(news_id) + '/edit'):
    editor_form = request(path)[0]
    assert 'data-gallery-picker' in editor_form and 'id="content-gallery-dialog"' in editor_form
    for mode in ('grid', 'slider', 'carousel', 'cards'):
        assert 'value="' + mode + '"' in editor_form
    assert 'Galleries.Admin.' not in editor_form and 'MCMS.common.' not in editor_form
    for script in re.findall(r'<script\b[^>]*>(.*?)</script>', editor_form, re.S):
        if script.strip():
            subprocess.run(['node', '--check'], input=script, text=True, check=True, capture_output=True)
picker_fixture_ids = []
for index in range(32):
    _, fixture_url = post('/admin/galleries/create', {'title': 'Picker fixture ' + str(index), 'status': 'published' if index < 31 else 'draft'})
    picker_fixture_ids.append(int(re.search(r'/([0-9]+)/edit$', fixture_url).group(1)))
gallery_picker = json.loads(request('/admin/galleries/picker?q=Picker%20fixture&page=1')[0])
assert len(gallery_picker['items']) == 30 and gallery_picker['lastPage'] == 2
second_page = json.loads(request('/admin/galleries/picker?q=Picker%20fixture&page=999')[0])
assert len(second_page['items']) == 1 and second_page['page'] == 2
assert len({item['id'] for item in gallery_picker['items'] + second_page['items']}) == 31
assert picker_fixture_ids[-1] not in {item['id'] for item in gallery_picker['items'] + second_page['items']}
assert json.loads(request('/admin/galleries/picker?q=missing-picker-album')[0])['items'] == []
for fixture_id in picker_fixture_ids:
    post('/admin/galleries/' + str(fixture_id) + '/delete', {}, '/admin/galleries')
page_fields['body'] = body + '<p>' + shortcode + '</p>'
post('/admin/pages/' + str(page_id) + '/edit', page_fields)
post('/admin/news/' + str(news_id) + '/edit', {**news_fields, 'body': '<p>Embedded news ' + shortcode + '</p>'})
assert shortcode not in request('/news')[0]
for path in ('/', '/site-smoke-page-renamed', '/news/site-smoke-article'):
    rendered = request(path)[0]
    assert 'data-gallery-id="' + str(album_id) + '"' in rendered
    assert 'First caption' in rendered and shortcode not in rendered
album_public = request('/galleries/site-smoke-album')[0]
assert album_public.index('Second caption') < album_public.index('First caption')
assert 'data-theme="mcms-default"' in album_public
assert 'Site smoke album' in request('/galleries')[0]
resources = json.loads(request('/admin/menus/resources?provider=galleries.album&q=Site%20smoke')[0])
assert 'Site smoke album' in str(resources)
module('galleries', 'disable')
expect_404('/galleries/site-smoke-album')
expect_404('/admin/galleries/picker')
expect_404('/galleries/assets/display.css')
assert 'data-gallery-picker' not in request('/admin/pages/' + str(page_id) + '/edit')[0]
assert 'data-gallery-picker' not in request('/admin/news/' + str(news_id) + '/edit')[0]
assert shortcode not in request('/site-smoke-page-renamed')[0]
assert 'data-gallery-id=' not in request('/site-smoke-page-renamed')[0]
module('galleries')
assert 'First caption' in request('/galleries/site-smoke-album')[0]

# Activate the second theme through its real administration action and preserve the shortcode contract.
post('/admin/themes/mcms-signature/activate', {}, '/admin/themes')
for path in ('/', '/site-smoke-page-renamed', '/news', '/news/site-smoke-article', '/galleries', '/galleries/site-smoke-album', '/contact'):
    rendered = request(path)[0]
    assert 'data-theme="mcms-signature"' in rendered, path
    assert 'Signature.' not in rendered, path
    assert len(re.findall(r'<h1\b', rendered)) == 1, path
assert 'sig-gallery-embed' in request('/site-smoke-page-renamed')[0]
for theme in ('mcms-default', 'mcms-signature'):
    post('/admin/themes/' + theme + '/activate', {}, '/admin/themes')
    for mode in ('slider', 'carousel', 'cards'):
        display_shortcode = '[gallery id="' + str(album_id) + '" view="' + mode + '"]'
        post('/admin/pages/' + str(page_id) + '/edit', {**page_fields, 'body': '<p>Display page ' + display_shortcode + '</p>'})
        post('/admin/news/' + str(news_id) + '/edit', {**news_fields, 'body': '<p>Display news ' + display_shortcode + '</p>'})
        for path in ('/', '/site-smoke-page-renamed', '/news/site-smoke-article'):
            rendered = request(path)[0]
            assert 'mcms-gallery-' + mode in rendered and display_shortcode not in rendered
            assert 'First caption' in rendered and 'Second caption' in rendered
            assert '/galleries/assets/display.js?v=' in html.unescape(rendered) and '/galleries/assets/display.css?v=' in html.unescape(rendered)
    post('/admin/pages/' + str(page_id) + '/edit', page_fields)
    post('/admin/news/' + str(news_id) + '/edit', {**news_fields, 'body': '<p>Embedded news ' + shortcode + '</p>'})
assert len(request('/galleries/assets/display.css')[0]) > 100
display_script = request('/galleries/assets/display.js')[0]
subprocess.run(['node', '--check'], input=display_script, text=True, check=True, capture_output=True)
for asset, mime in (('display.css', 'text/css'), ('display.js', 'text/javascript')):
    response = opener.open(urllib.request.Request(base + '/galleries/assets/' + asset, method='HEAD'))
    assert response.headers.get_content_type() == mime
    assert response.headers['Cache-Control'] == 'private, max-age=86400'
expect_404('/galleries/assets/Module.php')
for asset in ('css/theme.css', 'js/theme.js', 'mark.svg', 'screenshot.svg'):
    assert len(request('/assets/themes/mcms-signature/' + asset)[0]) > 100
post('/admin/themes/mcms-default/activate', {}, '/admin/themes')
assert 'data-theme="mcms-default"' in request('/site-smoke-page-renamed')[0]
post('/admin/themes/mcms-signature/activate', {}, '/admin/themes')

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
news_fields['featured_media_id'] = second['id']
seo_rules = {}
for kind in ('page', 'news', 'home'):
    for key, value in {'title': '{title} | {site_name}', 'description': 'About {title}',
                       'keywords': '', 'keywords_source': 'tags'}.items():
        seo_rules['rules[' + kind + '][' + key + ']'] = value
saved, _ = post('/admin/seo/settings', seo_rules, '/admin/seo')
assert 'SEO settings saved.' in saved
invalid, _ = post('/admin/seo/settings', {**seo_rules, 'rules[news][title]': '{bad_token}'}, '/admin/seo')
assert 'Unknown template variable.' in invalid
for theme in ('mcms-default', 'mcms-signature'):
    post('/admin/themes/' + theme + '/activate', {}, '/admin/themes')
    for kind, fields, content_id, public_path in [('pages', page_fields, page_id, '/site-smoke-page-renamed'),
                                                ('news', news_fields, news_id, '/news/site-smoke-article')]:
        form_path = '/admin/' + kind + '/' + str(content_id) + '/edit'
        form = request(form_path)[0]
        assert 'data-content-preview' in form
        preview_fields = {**fields, 'content_id': content_id, 'title': 'Unsaved preview',
                          'body': '<p>Private preview</p>' + '[gallery id="' + str(album_id) + '" view="carousel"]' + '<script>alert(1)</script>',
                          'tags': 'CMS, news', 'seo_title': '', 'seo_keywords': ''}
        result, _ = post('/admin/' + kind + '/preview', preview_fields, form_html=form)
        preview = json.loads(result)
        assert 'data-theme="' + theme + '"' in preview['html']
        assert '<title>Unsaved preview | ' in preview['html']
        assert 'noindex, nofollow, noarchive' in preview['html'] and 'mcms-gallery-carousel' in preview['html']
        assert 'Private preview' in preview['html'] and 'alert(1)' not in preview['html']
        assert 'name="keywords" content="CMS, news"' in html.unescape(preview['html'])
        assert 'Private preview' not in request(public_path)[0]
        # Preview returns the rotated CSRF token, allowing a subsequent save from the same editor.
        refreshed = re.sub(r'(name="csrf_test_name"[^>]*value=")[^"]+', r'\g<1>' + preview['csrfHash'], form)
        saved, _ = post(form_path, {**fields, 'tags': 'CMS, news', 'seo_keywords': 'manual keywords', 'seo_title': 'Exact title'}, form_html=refreshed)
        assert 'Exact title' in saved
        public = html.unescape(request(public_path)[0])
        assert '<title>Exact title</title>' in public and 'name="keywords" content="manual keywords"' in public
        for script in re.findall(r'<script\b[^>]*>(.*?)</script>', form, re.S):
            if script.strip(): subprocess.run(['node', '--check'], input=script, text=True, check=True, capture_output=True)
        post(form_path, fields)

# A create-only editor cannot preview an existing record by posting its ID.
created_user, _ = post('/admin/users/create', {'email': 'previewer@example.test', 'username': 'previewer',
                                             'password': 'Preview-Smoke-2026!'}, '/admin/users/create')
assert 'User created.' in created_user
row = next(row for row in re.findall(r'<tr\b.*?</tr>', created_user, re.S) if 'previewer' in row)
previewer_id = re.search(r'/admin/users/([0-9]+)/access', row).group(1)
post('/admin/users/' + previewer_id + '/access', [('groups[]', 'user'), ('permissions[]', 'core.admin'),
     ('permissions[]', 'pages.create'), ('permissions[]', 'news.create')])
admin_opener = opener
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
try:
    post('/login', {'email': 'previewer@example.test', 'password': 'Preview-Smoke-2026!'}, '/login')
    for kind, content_id in [('pages', page_id), ('news', news_id)]:
        fields = {'title': 'Create-only draft preview', 'body': '<p>Private draft</p>', 'status': 'draft'}
        try:
            post('/admin/' + kind + '/preview', {**fields, 'content_id': content_id}, '/admin/' + kind + '/create')
            raise AssertionError('Create-only editor previewed an existing record')
        except urllib.error.HTTPError as error:
            assert error.code == 403 and 'no-store' in error.headers.get('Cache-Control', '')
        result, _ = post('/admin/' + kind + '/preview', fields, '/admin/' + kind + '/create')
        assert 'Private draft' in json.loads(result)['html']
        assert '1970' not in json.loads(result)['html']
finally:
    opener = admin_opener

print('Site HTTP smoke passed: SEO metadata/rules, private unsaved previews, group management, Media, Galleries, Home, Feedback, sitemap, redirects and both themes.')
