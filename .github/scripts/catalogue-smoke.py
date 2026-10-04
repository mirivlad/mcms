#!/usr/bin/env python3
"""Verify catalogue and product-image forms on an isolated disposable mCMS stack."""
import argparse
import html
import http.cookiejar
import json
import re
import struct
import subprocess
import tempfile
import urllib.error
import urllib.parse
import urllib.request
import zlib
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', default='http://127.0.0.1:8080')
parser.add_argument('--cookies')
parser.add_argument('--artifacts', help='Optional directory for anonymous UI fixtures and evidence')
args = parser.parse_args()
base = args.base_url.rstrip('/')
jar = http.cookiejar.MozillaCookieJar(args.cookies) if args.cookies else http.cookiejar.CookieJar()
if args.cookies:
    jar.load(ignore_discard=True, ignore_expires=True)
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
public = urllib.request.build_opener()


def request(path, data=None, headers=None, anonymous=False):
    response = (public if anonymous else opener).open(urllib.request.Request(base + path, data=data, headers=headers or {}), timeout=60)
    return response.read(), response.url


def page(path):
    return request(path)[0].decode()


def token(source):
    return html.unescape(re.search(r'<input\b[^>]*name="csrf_test_name"[^>]*value="([^"]+)"', source)[1])


def post(path, fields, form_path):
    pairs = list(fields.items()) if isinstance(fields, dict) else list(fields)
    pairs.append(('csrf_test_name', token(page(form_path))))
    return request(path, urllib.parse.urlencode(pairs).encode())[0].decode()


def module(name, action='enable'):
    post('/admin/modules/' + name + '/' + action, {}, '/admin/modules')


def not_public(path):
    try:
        request(path, anonymous=True)
        raise AssertionError('Private path was public: ' + path)
    except urllib.error.HTTPError as error:
        assert error.code in (403, 404), (path, error.code)


def png(red, green, blue):
    width, height = 600, 400
    def chunk(kind, data):
        return struct.pack('>I', len(data)) + kind + data + struct.pack('>I', zlib.crc32(kind + data) & 0xffffffff)
    pixels = b''.join(b'\x00' + bytes((red, green, blue)) * width for _ in range(height))
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', width, height, 8, 2, 0, 0, 0)) + chunk(b'IDAT', zlib.compress(pixels)) + chunk(b'IEND', b'')


if not args.cookies:
    post('/install', {'site_name': 'Catalogue smoke', 'locale': 'en', 'email': 'commerce@example.test', 'username': 'commerce',
                     'password': 'Local-Commerce-2026!', 'password_confirm': 'Local-Commerce-2026!'}, '/install')
module('catalogue')
module('seo')
categories_path = '/admin/catalogue/categories'
store_fixture = page('/admin/catalogue')
assert 'Store management' in store_fixture and 'Manage categories' in store_fixture and 'Manage products' in store_fixture
products_fixture = page('/admin/catalogue/products')
assert 'Back to store management' in products_fixture and 'Manage categories' not in products_fixture
assert 'Back to store management' in page(categories_path)
assert 'Add category' in page(categories_path)


def save_category(fields, category_id=None, image=None):
    path = categories_path + ('/create' if category_id is None else f'/{category_id}/edit')
    fields = {**fields, 'csrf_test_name': token(page(path))}
    if image is None:
        body = urllib.parse.urlencode(fields).encode()
        headers = {}
    else:
        boundary = 'MCMSCategorySmokeBoundary'
        body = bytearray()
        for key, value in fields.items():
            body.extend(('--' + boundary + '\r\nContent-Disposition: form-data; name="' + key + '"\r\n\r\n' + str(value) + '\r\n').encode())
        body.extend(('--' + boundary + '\r\nContent-Disposition: form-data; name="image"; filename="category.png"\r\nContent-Type: image/png\r\n\r\n').encode())
        body.extend(image)
        body.extend(('\r\n--' + boundary + '--\r\n').encode())
        headers = {'Content-Type': 'multipart/form-data; boundary=' + boundary}
    response, url = request(path, bytes(body), headers)
    assert 'Category saved.' in response.decode(), response.decode()[-3000:]
    return int(re.search(r'/categories/(\d+)/edit', url)[1])


root_fields = {'name': 'Drinks', 'slug': 'drinks', 'parent_id': '', 'icon_type': 'tabler', 'icon': 'cup', 'icon_color': '#e24c27'}
root_id = save_category(root_fields)
child_fields = {'name': 'Coffee', 'slug': 'coffee', 'parent_id': root_id, 'icon_type': 'image', 'icon': 'coffee', 'icon_color': '#206bc4'}
child_id = save_category(child_fields, image=png(30, 98, 130))
category_editor = page(categories_path + f'/{child_id}/edit')
assert f'value="{root_id}" selected' in category_editor and 'name="icon_type"' in category_editor
assert 'data-open-icon-picker' in category_editor and 'type="color"' in category_editor
icon_bytes = request(f'/shop/category-icons/{child_id}', anonymous=True)[0]
assert icon_bytes.startswith(b'RIFF') and icon_bytes[8:12] == b'WEBP'
assert len(json.loads(request('/shop/assets/tabler-icons.json', anonymous=True)[0])) > 5000
category_form_fixture = page(categories_path + '/create')
category_list_fixture = page(categories_path)
editor = page('/admin/catalogue/create')
assert 'Product photos' in editor and 'data-drop-zone' in editor and 'data-add-variant' in editor
assert 'Manage categories' not in editor and '/admin/catalogue/products' in editor
assert 'Upload an image' in category_form_fixture
assert re.search(r'<div[^>]*data-image-fields[^>]*>', category_form_fixture)[0].find('hidden') == -1
set_id = re.search(r'name="image_set_id" value="([a-f0-9]+)"', editor)[1]
csrf = token(editor)
images_base = '/admin/product-images/' + set_id


def image_post(path, fields=None, file=None, name='photo.png', expect_error=False):
    global csrf
    fields = dict(fields or {})
    fields['csrf_test_name'] = csrf
    boundary = 'MCMSCommerceSmokeBoundary'
    body = bytearray()
    for key, value in fields.items():
        for entry in value if isinstance(value, list) else [value]:
            field_name = key + '[]' if isinstance(value, list) else key
            body.extend(('--' + boundary + '\r\nContent-Disposition: form-data; name="' + field_name + '"\r\n\r\n' + str(entry) + '\r\n').encode())
    if file is not None:
        body.extend(('--' + boundary + '\r\nContent-Disposition: form-data; name="file"; filename="' + name + '"\r\nContent-Type: image/png\r\n\r\n').encode())
        body.extend(file)
        body.extend(b'\r\n')
    body.extend(('--' + boundary + '--\r\n').encode())
    headers = {'Content-Type': 'multipart/form-data; boundary=' + boundary, 'X-Requested-With': 'XMLHttpRequest'}
    try:
        data = json.loads(request(images_base + '/' + path, bytes(body), headers)[0])
        assert not expect_error, data
    except urllib.error.HTTPError as error:
        assert expect_error and error.code == 422, (error.code, error.read().decode())
        data = json.loads(error.read())
        assert data['error']
    csrf = data['csrfHash']
    return data


first = image_post('upload', file=png(182, 66, 32), name='front.png')['items'][0]['id']
second = image_post('upload', file=png(30, 98, 130), name='side.png')['items'][1]['id']
third = image_post('upload', file=png(224, 177, 83), name='detail.png')['items'][2]['id']
image_post('upload', file=b'<svg>bad</svg>', name='bad.svg', expect_error=True)
image_post(f'{second}/crop', {'x': 100, 'y': 50, 'width': 300, 'height': 300, 'size': 300})
image_post('arrange', {'ids': [third, second, first], 'main_id': second})
image_post('arrange', {'ids': [second], 'main_id': second}, expect_error=True)
fields = {'title': 'Workshop coffee', 'slug': 'workshop-coffee', 'summary': 'Roasted beans for a slow morning.',
          'body': '<p>Fresh roast. Two sizes.</p><script>bad()</script>', 'category_id': child_id, 'status': 'draft', 'image_set_id': set_id,
          'variants[0][id]': '0', 'variants[0][sku]': 'COFFEE-250', 'variants[0][label]': '250 g', 'variants[0][price]': '1250,05',
          'variants[1][id]': '0', 'variants[1][sku]': 'COFFEE-500', 'variants[1][label]': '500 g', 'variants[1][price]': '2400'}
response, url = request('/admin/catalogue/create', urllib.parse.urlencode({**fields, 'csrf_test_name': csrf}).encode())
assert 'Product saved.' in response.decode(), response.decode()[-2000:]
product_id = int(re.search(r'/admin/catalogue/(\d+)/edit', url)[1])
edit_path = f'/admin/catalogue/{product_id}/edit'
reopened = page(edit_path)
config = json.loads(html.unescape(re.search(r'data-config="([^"]+)"', reopened)[1]))
assert [item['id'] for item in config['items']] == [third, second, first]
assert next(item for item in config['items'] if item['main'])['id'] == second
assert config['items'][1]['recipe']['width'] == 300
not_public('/shop/workshop-coffee')
not_public(f'/shop/workshop-coffee/images/{second}/full')
# Public access to editor files redirects to login and never serves image bytes.
private_bytes, private_url = request(images_base + f'/{second}/original', anonymous=True)
assert '/login' in private_url and not private_bytes.startswith(b'RIFF')
for index in (0, 1):
    fields[f'variants[{index}][id]'] = re.search(r'name="variants\[' + str(index) + r'\]\[id\]" value="(\d+)"', reopened)[1]
fields['status'] = 'published'
assert 'Product saved.' in post(edit_path, fields, edit_path)
public_product = html.unescape(request('/shop/workshop-coffee', anonymous=True)[0].decode())
assert 'Workshop coffee' in public_product and '1 250,05 ₽' in public_product
assert 'bad()' not in public_product
assert public_product.index(f'/images/{third}/full') < public_product.index(f'/images/{first}/full')
image_bytes = request(f'/shop/workshop-coffee/images/{second}/full', anonymous=True)[0]
assert image_bytes.startswith(b'RIFF') and image_bytes[8:12] == b'WEBP'
not_public(f'/shop/workshop-coffee/images/{second}/original')
not_public(f'/shop/workshop-coffee/images/999999/full')
listing = html.unescape(request('/shop', anonymous=True)[0].decode())
assert f'/images/{second}/display' in listing
assert 'Workshop coffee' in request('/search/results?q=Workshop', anonymous=True)[0].decode()
assert '/shop/workshop-coffee' in request('/sitemap.xml', anonymous=True)[0].decode()
assert f'value="{child_id}" selected' in page(edit_path)
root_page = html.unescape(request('/shop/category/drinks', anonymous=True)[0].decode())
assert 'Workshop coffee' in root_page and 'ti-cup' in root_page and '#e24c27' in root_page
assert f'/shop/category-icons/{child_id}' in root_page
assert 'Workshop coffee' in request('/shop/category/coffee', anonymous=True)[0].decode()
assert 'Workshop coffee' in request('/shop?category=drinks', anonymous=True)[0].decode()
assert 'Workshop coffee' not in request('/shop?category=missing', anonymous=True)[0].decode()
assert '/shop/category/drinks' in request('/sitemap.xml', anonymous=True)[0].decode()
# The server rejects cycles even if a client forges an unavailable parent option.
cycle = post(categories_path + f'/{root_id}/edit', {**root_fields, 'parent_id': child_id}, categories_path + f'/{root_id}/edit')
assert 'A category cannot be its own parent' in cycle
assert 'Move products and subcategories first.' in post(categories_path + f'/{root_id}/delete', {'confirm': 1}, categories_path)
assert 'Move products and subcategories first.' in post(categories_path + f'/{child_id}/delete', {'confirm': 1}, categories_path)
# Changing the name or slug retains the product association and redirects the old URL.
child_fields.update(name='Roasted coffee', slug='roasted-coffee')
save_category(child_fields, child_id)
assert request('/shop/category/coffee', anonymous=True)[1].endswith('/shop/category/roasted-coffee')
assert 'Roasted coffee' in page(edit_path)
assert 'Workshop coffee' in request('/shop/category/drinks', anonymous=True)[0].decode()
not_public('/shop/category/missing-category')
# Replacing an image changes the public revision; switching to Tabler removes it.
replaced_editor = html.unescape(page(categories_path + f'/{child_id}/edit'))
old_revision = re.search(r'/shop/category-icons/[^" ]+', replaced_editor)[0]
save_category(child_fields, child_id, png(220, 50, 30))
assert old_revision not in html.unescape(page(categories_path + f'/{child_id}/edit'))
save_category({**child_fields, 'icon_type': 'tabler', 'icon': 'coffee', 'icon_color': '#123abc'}, child_id)
not_public(f'/shop/category-icons/{child_id}')
assert 'ti-coffee' in request('/shop/category/roasted-coffee', anonymous=True)[0].decode()
# Empty categories can be removed through the confirmed form, including their image.
empty_id = save_category({**child_fields, 'name': 'Empty', 'slug': 'empty', 'parent_id': ''}, image=png(1, 2, 3))
assert 'Category deleted.' in post(categories_path + f'/{empty_id}/delete', {'confirm': 1}, categories_path)
not_public(f'/shop/category-icons/{empty_id}')
not_public('/shop/category/empty')
# Repeat processing and replacement retain IDs, order and main image after saving.
csrf = token(page(edit_path))
image_post('upload', {'replace_id': first}, file=png(120, 68, 46), name='new-front.png')
image_post(f'{first}/crop', {'x': 0, 'y': 0, 'width': 600, 'height': 400, 'size': 1200})
csrf = token(page(edit_path))
image_post(f'{third}/remove')
csrf = token(page(edit_path))
third = image_post('upload', file=png(224, 177, 83), name='new-detail.png')['items'][-1]['id']
image_post('arrange', {'ids': [third, second, first], 'main_id': second})
fields['slug'] = 'artisan-coffee'
assert 'Product saved.' in post(edit_path, fields, edit_path)
assert request('/shop/workshop-coffee', anonymous=True)[1].endswith('/shop/artisan-coffee')
# Media can be disabled even while Catalogue is enabled.
for name in ('news', 'galleries', 'media'):
    module(name, 'disable')
assert 'Workshop coffee' in request('/shop/artisan-coffee', anonymous=True)[0].decode()
public_saved = {}
for theme in ('mcms-default', 'mcms-signature', 'mcms-depth', 'mcms-focus'):
    post('/admin/themes/' + theme + '/activate', {}, '/admin/themes')
    rendered = request('/shop/artisan-coffee', anonymous=True)[0].decode()
    assert f'data-theme="{theme}"' in rendered and 'data-product-gallery' in rendered
    assert 'Workshop coffee' in request('/shop', anonymous=True)[0].decode()
    assert 'Workshop coffee' in request('/shop/category/drinks', anonymous=True)[0].decode()
    public_saved[theme] = rendered
module('catalogue', 'disable')
not_public('/shop')
module('catalogue')
assert 'Workshop coffee' in request('/shop/artisan-coffee', anonymous=True)[0].decode()
reopened = page(edit_path)
for script in re.findall(r'<script\b[^>]*>(.*?)</script>', reopened, re.S):
    if script.strip():
        subprocess.run(['node', '--check'], input=script, text=True, capture_output=True, check=True)
if args.artifacts:
    destination = Path(args.artifacts)
    destination.mkdir(parents=True, exist_ok=True)
    evidence_jar = http.cookiejar.MozillaCookieJar(str(destination / 'test.cookies'))
    for cookie in jar: evidence_jar.set_cookie(cookie)
    evidence_jar.save(ignore_discard=True, ignore_expires=True)
    (destination / 'editor.html').write_text(reopened)
    (destination / 'category-form.html').write_text(category_form_fixture)
    (destination / 'store.html').write_text(store_fixture)
    (destination / 'products.html').write_text(products_fixture)
    (destination / 'categories.html').write_text(category_list_fixture)
    (destination / 'config.json').write_text(json.dumps(json.loads(html.unescape(re.search(r'data-config="([^"]+)"', reopened)[1]))))
    for theme, rendered in public_saved.items():
        (destination / (theme + '.html')).write_text(rendered)
    for item in json.loads((destination / 'config.json').read_text())['items']:
        for size in ('thumb', 'display', 'full', 'original'):
            (destination / f"{item['id']}-{size}.webp").write_bytes(request(images_base + f"/{item['id']}/{size}")[0])
# Photo permissions are independent from the right to create/edit product text.
created_user = post('/admin/users/create', {'email': 'catalogue-editor@example.test', 'username': 'catalogue.editor',
                   'password': 'Catalogue-Editor-2026!'}, '/admin/users/create')
assert 'User created.' in created_user
row = next(row for row in re.findall(r'<tr\b.*?</tr>', created_user, re.S) if 'catalogue.editor' in row)
editor_id = re.search(r'/admin/users/([0-9]+)/access', row)[1]
permissions = [('groups[]', 'user'), ('permissions[]', 'core.admin'), ('permissions[]', 'catalogue.create')]
post('/admin/users/' + editor_id + '/access', permissions, '/admin/users/' + editor_id + '/access')
admin_opener = opener
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
try:
    post('/login', {'email': 'catalogue-editor@example.test', 'password': 'Catalogue-Editor-2026!'}, '/login')
    limited = page('/admin/catalogue/create')
    assert 'Managing photos requires' in limited and 'data-config=' not in limited
    limited_csrf = token(limited)
    try:
        request(images_base + '/arrange', urllib.parse.urlencode({'csrf_test_name': limited_csrf, 'main_id': first}).encode())
        raise AssertionError('Editor without photo permissions reordered photos')
    except urllib.error.HTTPError as error:
        assert error.code == 403 and json.loads(error.read())['csrfHash']
    assert request(edit_path)[1] == base + '/'
finally:
    opener = admin_opener
# Even an editor with photo permissions cannot attach another user's pending image set.
foreign_editor = page('/admin/catalogue/create')
foreign_set = re.search(r'name="image_set_id" value="([a-f0-9]+)"', foreign_editor)[1]
post('/admin/users/' + editor_id + '/access', permissions + [('permissions[]', 'product-images.manage')], '/admin/users/' + editor_id + '/access')
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
try:
    post('/login', {'email': 'catalogue-editor@example.test', 'password': 'Catalogue-Editor-2026!'}, '/login')
    attempted = {**fields, 'title': 'Forbidden set', 'slug': 'forbidden-set', 'image_set_id': foreign_set,
                 'variants[0][id]': 0, 'variants[0][sku]': 'FORBIDDEN-250', 'variants[1][id]': 0, 'variants[1][sku]': 'FORBIDDEN-500'}
    denied = post('/admin/catalogue/create', attempted, '/admin/catalogue/create')
    assert 'You cannot access this image set.' in denied
    not_public('/shop/forbidden-set')
finally:
    opener = admin_opener

# Fixture remains published for optional anonymous visual checks. Validate destructive flow on a second product.
new_editor = page('/admin/catalogue/create')
new_set = re.search(r'name="image_set_id" value="([a-f0-9]+)"', new_editor)[1]
deletion_fields = {**fields, 'title': 'Delete fixture', 'slug': 'delete-fixture', 'image_set_id': new_set,
                   'variants[0][id]': 0, 'variants[0][sku]': 'DELETE-250', 'variants[1][id]': 0, 'variants[1][sku]': 'DELETE-500'}
_, delete_url = request('/admin/catalogue/create', urllib.parse.urlencode({**deletion_fields, 'csrf_test_name': token(new_editor)}).encode())
delete_id = re.search(r'/admin/catalogue/(\d+)/edit', delete_url)[1]
result = post('/admin/catalogue/' + delete_id + '/delete', {'confirm': '1'}, '/admin/catalogue/products')
assert 'Product and its photos deleted.' in result
not_public('/shop/delete-fixture')
# Restore content dependencies in CI's shared disposable fixture.
if args.cookies:
    for name in ('media', 'news', 'galleries'):
        module(name)
print('Catalogue HTTP smoke passed: upload/crop/order/main/replace/remove, variants/prices, nested categories/icons/colors/images/guards/redirects, draft privacy, SEO/search, Media independence, four themes, disable/re-enable and deletion.')
