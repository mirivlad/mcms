#!/usr/bin/env python3
"""Exercise ordered resource-specific menus through real local HTTP forms."""
import argparse
import html
import http.cookiejar
import json
import re
import time
import urllib.error
import urllib.parse
import urllib.request

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', default='http://127.0.0.1:18085')
parser.add_argument('--cookies', required=True)
args = parser.parse_args()
base = args.base_url.rstrip('/')
jar = http.cookiejar.MozillaCookieJar(args.cookies)
jar.load(ignore_discard=True, ignore_expires=True)


class LocalRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, message, headers, new_url):
        return super().redirect_request(request, fp, code, message, headers, urllib.parse.urldefrag(new_url)[0])


client = urllib.request.build_opener(LocalRedirect(), urllib.request.HTTPCookieProcessor(jar))


def get(path):
    return html.unescape(client.open(base + path, timeout=30).read().decode())


def post(path, fields, source, content=None):
    content = content or get(source)
    csrf = re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', content)[1]
    pairs = list(fields.items()) if isinstance(fields, dict) else list(fields)
    response = client.open(base + path, urllib.parse.urlencode(pairs + [('csrf_test_name', csrf)]).encode(), timeout=30)
    return html.unescape(response.read().decode()), response.url


post('/admin/modules/menus/enable', {}, '/admin/modules')
nonce = str(time.time_ns())[-10:]
menus = []
for index in range(5):
    label = 'Navigation base' if index == 0 else 'Navigation extra ' + str(index)
    _, url = post('/admin/menus/create', {'name': label, 'handle': 'nav-' + nonce + '-' + str(index)}, '/admin/menus/create')
    mid = re.search(r'/admin/menus/(\d+)/edit', url)[1]
    edit = '/admin/menus/' + mid + '/edit'
    post('/admin/menus/' + mid + '/items/save', {'label': label, 'target_type': 'custom', 'target': '/navigation-' + str(index), 'enabled': '1'}, edit)
    menus.append(mid)
page_id = re.search(r'/admin/pages/(\d+)/edit', get('/admin/pages'))[1]
edit = get('/admin/pages/' + page_id + '/edit')
slug = re.search(r'name="slug"[^>]*value="([^"]+)"', edit)[1]
endpoint = '/admin/themes/package/resources?provider=pages.page&q=' + urllib.parse.quote('Interaction')
assert any(str(item['id']) == page_id for item in json.loads(get(endpoint))['items'])
# Give a published Catalogue category its own resource assignment as well.
_, url = post('/admin/catalogue/categories/create', {'name': 'Navigation category', 'slug': 'nav-' + nonce, 'parent_id': '', 'icon_type': 'tabler', 'icon': 'folder', 'icon_color': '#206bc4'}, '/admin/catalogue/categories/create')
category_id = re.search(r'/admin/catalogue/categories/(\d+)/edit', url)[1]
form = get('/admin/catalogue/create')
image_set = re.search(r'name="image_set_id" value="([^"]+)"', form)[1]
post('/admin/catalogue/create', {'title': 'Navigation product', 'slug': 'nav-product-' + nonce, 'status': 'published', 'category_id': category_id, 'image_set_id': image_set, 'variants[0][sku]': 'NAV-' + nonce, 'variants[0][label]': 'One', 'variants[0][price]': '100'}, '/admin/catalogue/create', form)
news_slug = 'nav-news-' + nonce
_, news_url = post('/admin/news/create', {'title': 'Navigation news', 'slug': news_slug,
    'body': '<p>Navigation news body</p>', 'status': 'published',
    'published_at': time.strftime('%Y-%m-%d %H:%M:%S', time.gmtime(time.time() - 60))}, '/admin/news/create')
news_id = re.search(r'/admin/news/(\d+)/edit', news_url)[1]
assignments = [
    ('menu:' + menus[3], 'all', '', ''),
    ('menu:' + menus[1], 'all', '', ''),
    ('menu:' + menus[2], 'all', '', ''),
    ('menu:' + menus[4], 'resource', 'pages.page', page_id),
    ('menu:' + menus[4], 'resource', 'catalogue.category', category_id),
    ('menu:' + menus[4], 'resource', 'news.article', news_id),
]
fields = [('location[]', 'footer.primary'), ('menu_id[]', menus[0])]
for source, visibility, target_type, target_id in assignments:
    fields += [('extra_location[]', 'footer.primary'), ('extra_source[]', source), ('extra_visibility[]', visibility), ('extra_target_type[]', target_type), ('extra_target_id[]', target_id)]
for theme in ['mcms-default', 'mcms-depth', 'mcms-focus', 'mcms-signature', 'mcms-atelier']:
    path = '/admin/themes/' + theme
    result, _ = post(path, fields + [('menu_form_version', '2'), ('menu_form_complete', '1')], path)
    assert 'value="' + page_id + '" selected' in result
    post(path + '/activate', {}, '/admin/themes')
    public = get('/' + slug)
    labels = ['Navigation base', 'Navigation extra 3', 'Navigation extra 1', 'Navigation extra 2', 'Navigation extra 4']
    positions = [public.index(label) for label in labels]
    assert positions == sorted(positions), (theme, positions)
    assert 'Navigation extra 4' not in get('/contact')
    assert 'Navigation extra 4' in get('/shop/category/nav-' + nonce)
    assert 'Navigation extra 4' in get('/news/' + news_slug)
path = '/admin/themes/mcms-atelier'
# A truncated modern form must not clear saved rows.
result, _ = post(path, [('menu_form_version', '2')], path)
assert 'No settings were saved' in result
assert get('/' + slug).count('Navigation extra 4') == 1
post('/admin/modules/menus/disable', {}, '/admin/modules')
assert 'Navigation extra 4' not in get('/' + slug)
assert 'menu:' + menus[4] in get(path)
post('/admin/modules/menus/enable', {}, '/admin/modules')
assert 'Navigation extra 4' in get('/' + slug)
jar.save(ignore_discard=True, ignore_expires=True)
print('Resource menus HTTP smoke passed: six assignments, order, page/news/category visibility, five themes, truncation guard and module lifecycle.')
print('Browser page: ' + base + '/' + slug)
print('Browser category: ' + base + '/shop/category/nav-' + nonce)
