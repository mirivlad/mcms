#!/usr/bin/env python3
"""Exercise API keys, real checkout/reservations and concurrent HTTP exchange."""
import argparse
from concurrent.futures import ThreadPoolExecutor
import html
import http.cookiejar
import json
import re
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', default='http://127.0.0.1:8080')
parser.add_argument('--cookies', required=True)
parser.add_argument('--state-file', help='Private temporary receipt/key state for app recreation verification')
parser.add_argument('--phase', choices=['exercise', 'persist'], default='exercise')
args = parser.parse_args()
base = args.base_url.rstrip('/')
run_id = uuid.uuid4().hex[:8]
slug = 'api-smoke-product-' + run_id
sku = 'API-SMOKE-1-' + run_id.upper()
courier = 'API smoke courier ' + run_id
jar = http.cookiejar.MozillaCookieJar(args.cookies)
jar.load(ignore_discard=True, ignore_expires=True)
admin = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
guest = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def fetch(path, client=admin, data=None, headers=None, method=None):
    request = urllib.request.Request(base + path, data=data, headers=headers or {}, method=method)
    try:
        response = client.open(request, timeout=60)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, response.read().decode(), response.url, response.headers


def page(path, client=admin):
    status, body, _, _ = fetch(path, client)
    assert status == 200, (path, status, body[-1000:])
    return body


def post(path, fields, form, client=admin):
    source = page(form, client)
    csrf = html.unescape(re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', source)[1])
    pairs = list(fields.items()) if isinstance(fields, dict) else list(fields)
    pairs.append(('csrf_test_name', csrf))
    status, body, url, _ = fetch(path, client, urllib.parse.urlencode(pairs).encode())
    assert status == 200, (path, status, body[-1000:])
    return body, url


def module(name, action='enable'):
    post('/admin/modules/' + name + '/' + action, {}, '/admin/modules')


def create_key(name, scopes):
    name += ' ' + run_id
    fields = [('name', name), ('expires', '')] + [('scopes[]', scope) for scope in scopes]
    body, _ = post('/admin/api/keys', fields, '/admin/api')
    token = re.search(r'id="api-secret"[^>]*value="([^"]+)"', body)
    assert token, body[-1000:]
    key = token[1]
    assert key not in page('/admin/api'), 'Secret must only be displayed once'
    source = page('/admin/api')
    row = next(row for row in re.findall(r'<tr\b.*?</tr>', source, re.S) if name in row)
    key_id = int(re.search(r'/keys/(\d+)/revoke', row)[1])
    return key, key_id


def api(path, key=None, body=None, operation=None, expected=200, raw=None, content_type='application/json'):
    headers = {'Accept': 'application/json'}
    if key is not None:
        headers['Authorization'] = 'Bearer ' + key
    data = None
    if body is not None or raw is not None:
        data = json.dumps(body).encode() if raw is None else raw
        headers['Content-Type'] = content_type
    if operation is not None:
        headers['Idempotency-Key'] = operation
    # A new connection without browser cookies per request, also for race probes.
    status, response, _, response_headers = fetch('/api/v1' + path, urllib.request.build_opener(), data, headers, 'PUT' if data is not None else 'GET')
    assert status == expected, (path, status, response)
    result = json.loads(response)
    assert 'no-store' in response_headers.get('Cache-Control', '')
    if status == 200:
        assert result['meta']['api_version'] == '1'
    else:
        assert result['error']['code']
    return result


if args.phase == 'persist':
    assert args.state_file, '--state-file is required for persistence verification'
    state = json.loads(Path(args.state_file).read_text())
    assert api('/stock/' + str(state['variant']), state['key'])['data']['on_hand'] == 5
    assert api('/stock/' + str(state['variant']), state['key'], state['body'], 'persist-receipt')['data'] == state['receipt']
    assert api('/orders/' + str(state['order']), state['key'])['data']['status'] == 'completed'
    post(f"/admin/api/keys/{state['key_id']}/revoke", {}, '/admin/api')
    api('/stock', state['key'], expected=401)
    jar.save(ignore_discard=True, ignore_expires=True)
    Path(args.state_file).unlink()
    print('API key, receipt, stock and saved order survived application recreation.')
    raise SystemExit(0)

module('api')
module('delivery')
full, full_id = create_key('API smoke accounting', ['stock.read', 'stock.write', 'orders.read', 'delivery.read'])
reader, reader_id = create_key('API smoke reader', ['stock.read'])
second, second_id = create_key('API smoke second writer', ['stock.read', 'stock.write'])
api('/stock', expected=401)
status, _, _, _ = fetch('/api/v1/stock')
assert status == 401, 'Session cookies must not authorize API requests'
api('/stock?token=' + urllib.parse.quote(full), expected=401)
api('/orders', reader, expected=403)
api('/stock?limit=201', full, expected=422)
api('/stock?after_id[]=1', full, expected=422)
api('/stock/999999999999999999999999', full, expected=422)
api('/orders?updated_since=2026-02-30%2000:00:00', full, expected=422)
api('/orders?status=unknown', full, expected=422)

# Create the fixture through the real product editor; no commerce table writes.
fields = {'title': 'API smoke product', 'slug': slug, 'summary': '', 'body': '',
          'category_id': '', 'status': 'published',
          'variants[0][id]': '0', 'variants[0][sku]': sku, 'variants[0][label]': 'Unit', 'variants[0][price]': '123.45',
          'variants[1][id]': '0', 'variants[1][sku]': 'API-SMOKE-2-' + run_id, 'variants[1][label]': 'Box', 'variants[1][price]': '234.56'}
fields['image_set_id'] = re.search(r'name="image_set_id" value="([^"]+)"', page('/admin/catalogue/create'))[1]
body, url = post('/admin/catalogue/create', fields, '/admin/catalogue/create')
assert 'Product saved.' in body, re.findall(r'<div[^>]*alert[^>]*>.*?</div>', body, re.S)
assert 'Product saved.' in body
product_id = int(re.search(r'/catalogue/(\d+)/edit', url)[1])
variant_ids = [int(re.search(r'name="variants\[' + str(index) + r'\]\[id\]" value="(\d+)"', body)[1]) for index in (0, 1)]
variant = variant_ids[0]
path = '/stock/' + str(variant)
assert api(path, full)['data']['on_hand'] == 0
initial = api(path, full, {'on_hand': 10, 'expected_on_hand': 0}, 'initial')['data']
assert initial['available'] == 10
api(path, reader, {'on_hand': 11, 'expected_on_hand': 10}, 'denied', expected=403)
api(path, full, {'on_hand': 11, 'expected_on_hand': 10}, expected=422)
api(path, full, raw=b'{broken', operation='bad-json', expected=400)
api(path, full, raw=b'[]', operation='bad-shape', expected=422)
api(path, full, raw=b'{}', operation='bad-type', content_type='text/plain', expected=415)
api(path, full, raw=b' ' * 16385, operation='too-large', expected=413)
for invalid in [{'on_hand': '11', 'expected_on_hand': 10}, {'on_hand': True, 'expected_on_hand': 10},
                {'on_hand': -1, 'expected_on_hand': 10}, {'on_hand': 11, 'expected_on_hand': 10, 'extra': 1}]:
    api(path, full, invalid, 'invalid', expected=422)
api('/stock/999999999', full, expected=404)
api('/orders/999999999', full, expected=404)

# Delivery snapshot belongs to the order, including when the method is edited.
post('/admin/delivery/create', {'name': courier, 'type': 'delivery', 'price': '5.67', 'position': 50, 'enabled': 1}, '/admin/delivery/create')
method = next(item for item in api('/delivery-methods', full)['data'] if item['name'] == courier)
post('/cart/add', {'variant_id': variant, 'quantity': 3, 'return': '/cart'}, '/shop/' + slug, guest)
body, _ = post('/order/checkout', {'name': 'API Customer', 'phone': '+70000000000', 'email': 'api-smoke@example.test',
                                  'comment': 'Door 8', 'delivery_method_id': method['id']}, '/cart', guest)
order_id = int(re.search(r'Order #(\d+) was created', body)[1])
order = api('/orders/' + str(order_id), full)['data']
assert order['customer']['comment'] == 'Door 8' and order['items'][0]['sku'] == sku
assert order['fulfillment']['name'] == courier and order['delivery_minor'] == 567
assert order['total_minor'] == 3 * 12345 + 567 and 'reservation_key' not in order
assert api(path, full)['data']['reserved'] == 3
api(path, full, {'on_hand': 2, 'expected_on_hand': 10}, 'below-reserved', expected=409)
receipt = api(path, full, {'on_hand': 12, 'expected_on_hand': 10}, 'receipt')['data']
assert [receipt['on_hand'], receipt['reserved'], receipt['available']] == [12, 3, 9]
post(f'/admin/delivery/{method["id"]}', {'name': 'Renamed API courier', 'type': 'delivery', 'price': '9.99', 'position': 50, 'enabled': 1}, f'/admin/delivery/{method["id"]}')
assert api('/orders/' + str(order_id), full)['data']['fulfillment']['name'] == courier
assert next(item for item in api('/delivery-methods', full)['data'] if item['id'] == method['id'])['price_minor'] == 999
module('delivery', 'disable')
api('/delivery-methods', full, expected=503)
assert api('/orders/' + str(order_id), full)['data']['fulfillment']['price_minor'] == 567
module('delivery')
post(f'/admin/orders/{order_id}/status', {'status': 'completed', 'note': 'API smoke consumption'}, f'/admin/orders/{order_id}')
assert api(path, full)['data']['on_hand'] == 9
assert api(path, full, {'expected_on_hand': 10, 'on_hand': 12}, 'receipt')['data'] == receipt
assert api(path, full)['data']['on_hand'] == 9, 'Retry must not resurrect consumed stock'
api(path, full, {'on_hand': 13, 'expected_on_hand': 9}, 'receipt', expected=409)
api(path, full, {'on_hand': 13, 'expected_on_hand': 10}, 'stale', expected=409)

# Independent credentials race with the same baseline: exactly one wins.
def race_writer(pair):
    key, quantity = pair
    headers = {'Authorization': 'Bearer ' + key, 'Content-Type': 'application/json', 'Idempotency-Key': 'race-' + str(quantity)}
    status, body, _, _ = fetch('/api/v1' + path, urllib.request.build_opener(), json.dumps({'on_hand': quantity, 'expected_on_hand': 9}).encode(), headers, 'PUT')
    return status, json.loads(body)

with ThreadPoolExecutor(max_workers=2) as workers:
    races = list(workers.map(race_writer, [(full, 20), (second, 21)]))
assert sorted(status for status, _ in races) == [200, 409], races
current = api(path, full)['data']['on_hand']
with ThreadPoolExecutor(max_workers=4) as workers:
    retries = list(workers.map(lambda _: api(path, full, {'on_hand': 30, 'expected_on_hand': current}, 'parallel-retry')['data'], range(4)))
assert all(result == retries[0] for result in retries)
assert api(path, full)['data']['on_hand'] == 30
# Idempotency keys are case-sensitive even under MariaDB's default collation.
api(path, full, {'on_hand': 31, 'expected_on_hand': 30}, 'Case-Key')
api(path, full, {'on_hand': 32, 'expected_on_hand': 31}, 'case-key')

items, after, upper = [], 0, None
while True:
    query = '/stock?' + urllib.parse.urlencode({'after_id': after, 'limit': 1, **({} if upper is None else {'until_id': upper})})
    data = api(query, full)['data']
    upper = data['until_id']
    items.extend(data['items'])
    after = data['next_cursor']
    if not data['has_more']:
        break
assert all(item in [row['variant_id'] for row in items] for item in variant_ids)
assert [row['variant_id'] for row in items] == sorted(set(row['variant_id'] for row in items))
changed = api('/orders?' + urllib.parse.urlencode({'updated_since': order['updated_at']}), full)['data']
assert any(item['id'] == order_id and item['status'] == 'completed' for item in changed['items'])

# Admin CSRF remains enforced while Bearer PUT deliberately needs no CSRF cookie.
status, _, _, _ = fetch('/admin/api/keys', data=b'name=without-csrf&expires=&scopes%5B%5D=stock.read')
assert status in (200, 302, 403), status
assert not any('without-csrf' in row for row in re.findall(r'<tr\b.*?</tr>', page('/admin/api'), re.S)), 'Admin write without CSRF was accepted'
post(f'/admin/api/keys/{reader_id}/revoke', {}, '/admin/api')
api('/stock', reader, expected=401)
module('api', 'disable')
status, _, _, _ = fetch('/api/v1/stock', urllib.request.build_opener())
assert status == 404, status
module('api')
assert api(path, full)['data']['on_hand'] == 32
assert api(path, full, {'expected_on_hand': 10, 'on_hand': 12}, 'receipt')['data'] == receipt
if args.state_file:
    persistent, persistent_id = create_key('API smoke persistence', ['stock.read', 'stock.write', 'orders.read'])
    persistent_body = {'on_hand': 5, 'expected_on_hand': 0}
    persistent_receipt = api('/stock/' + str(variant_ids[1]), persistent, persistent_body, 'persist-receipt')['data']
    state_path = Path(args.state_file)
    state_path.touch(mode=0o600)
    state_path.chmod(0o600)
    state_path.write_text(json.dumps({'key': persistent, 'key_id': persistent_id, 'variant': variant_ids[1], 'body': persistent_body,
                                      'receipt': persistent_receipt, 'order': order_id}))
for key_id in (full_id, second_id):
    post(f'/admin/api/keys/{key_id}/revoke', {}, '/admin/api')
api('/stock', full, expected=401)
jar.save(ignore_discard=True, ignore_expires=True)
print('API HTTP smoke passed: scopes, CSRF isolation, saved delivery, stock reservations, safe retries, concurrent MariaDB writers and module lifecycle.')
