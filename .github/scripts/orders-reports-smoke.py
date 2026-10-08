#!/usr/bin/env python3
"""Verify reports/CSV on a disposable stack after catalogue-smoke.py."""
import argparse
import csv
import html
import http.cookiejar
import io
import re
import urllib.error
import urllib.parse
import urllib.request

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', default='http://127.0.0.1:8080')
parser.add_argument('--cookies', required=True)
args = parser.parse_args()
base = args.base_url.rstrip('/')
jar = http.cookiejar.MozillaCookieJar(args.cookies)
jar.load(ignore_discard=True, ignore_expires=True)
owner = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))


def page(path, client=owner):
    response = client.open(base + path, timeout=30)
    return response.read().decode(), response.url


def post(path, fields, form_path, client=owner):
    source = page(form_path, client)[0]
    token = html.unescape(re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', source)[1])
    pairs = list(fields.items()) if isinstance(fields, dict) else list(fields)
    response = client.open(base + path, urllib.parse.urlencode(pairs + [('csrf_test_name', token)]).encode(), timeout=30)
    return response.read().decode()


def export(format_name, fields=None, client=owner):
    path = '/admin/orders/export?' + urllib.parse.urlencode({'from': '', 'to': '', 'format': format_name, **(fields or {})})
    response = client.open(base + path, timeout=30)
    assert response.headers['Content-Type'].startswith('text/csv'), response.headers
    assert 'attachment;' in response.headers['Content-Disposition']
    assert 'private' in response.headers['Cache-Control'] and 'no-store' in response.headers['Cache-Control']
    body = response.read()
    assert body.startswith(b'\xef\xbb\xbf')
    return list(csv.DictReader(io.StringIO(body.decode('utf-8-sig')), delimiter=';'))


def rejected(path, status, client=owner):
    try:
        client.open(base + path, timeout=30)
        raise AssertionError('Invalid report/export request accepted: ' + path)
    except urllib.error.HTTPError as error:
        assert error.code == status, (path, error.code)
        assert error.headers['Content-Disposition'] is None


report, _ = page('/admin/orders/reports?from=&to=')
assert 'Order report' in report and 'Orders CSV' in report and 'Order items CSV' in report
assert '/admin/orders/reports' in page('/admin/orders')[0]
orders = export('orders')
assert len(orders) >= 2
cancelled = next(row for row in orders if row['customer_name'] == 'Smoke Customer')
completed = next(row for row in orders if row['customer_name'] == 'Smoke Customer 2')
assert cancelled['status'] == 'cancelled' and completed['status'] == 'completed'
assert completed['fulfillment_name'] == 'City courier' and completed['fulfillment_type'] == 'delivery'
assert completed['subtotal_minor'] == '375015' and completed['delivery_minor'] == '50000' and completed['total_minor'] == '425015'
assert completed['phone'] == "'+1 555 0200"
items = export('items', {'status': 'completed'})
line = next(row for row in items if row['order_id'] == completed['order_id'])
assert line['product_title'] == 'Workshop coffee' and line['quantity'] == '3'
assert line['unit_price_minor'] == '125005' and line['line_subtotal_minor'] == '375015'
day = completed['created_at'][:10]
filtered = export('orders', {'from': day, 'to': day, 'status': 'completed'})
assert completed in filtered and cancelled not in filtered
assert all(row['status'] == 'completed' and row['created_at'].startswith(day) for row in filtered)
assert export('orders', {'from': '1900-01-01', 'to': '1900-01-01'}) == []
for query in ['from=2026-02-30', 'from=2026-10-09&to=2026-10-08', 'status=invalid', 'from%5B%5D=2026-10-08']:
    rejected('/admin/orders/reports?' + query, 422)
    rejected('/admin/orders/export?format=orders&' + query, 422)
rejected('/admin/orders/export?format=invalid', 422)
rejected('/admin/orders/export?format%5B%5D=orders', 422)

# Report readers do not acquire permission to download customer data.
source = post('/admin/users/create', {
    'username': 'reports.reader', 'email': 'reports-reader@example.test', 'password': 'Reports-Reader-2026!',
}, '/admin/users/create')
assert 'User created.' in source
row = next(row for row in re.findall(r'<tr\b.*?</tr>', source, re.S) if 'reports.reader' in row)
reader_id = re.search(r'/admin/users/(\d+)/access', row)[1]
access_path = '/admin/users/' + reader_id + '/access'
permissions = [('permissions[]', 'core.admin'), ('permissions[]', 'orders.view')]
post(access_path, permissions, access_path)
reader = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
post('/login', {'email': 'reports-reader@example.test', 'password': 'Reports-Reader-2026!'}, '/login', reader)
report, _ = page('/admin/orders/reports?from=&to=', reader)
assert 'Order report' in report and '/admin/orders/export' not in report
rejected('/admin/orders/export?format=orders&from=&to=', 403, reader)
post(access_path, permissions + [('permissions[]', 'orders.export')], access_path)
assert completed in export('orders', client=reader)
post(access_path, [('permissions[]', 'core.admin'), ('permissions[]', 'orders.export')], access_path)
rejected('/admin/orders/export?format=items&from=&to=', 403, reader)
assert page('/admin/orders/reports', reader)[1] == base + '/'

# Reports are read-only and remain available when Orders is re-enabled.
before = export('orders')
post('/admin/modules/delivery/disable', {}, '/admin/modules')
post('/admin/modules/orders/disable', {}, '/admin/modules')
rejected('/admin/orders/reports', 404)
post('/admin/modules/orders/enable', {}, '/admin/modules')
post('/admin/modules/delivery/enable', {}, '/admin/modules')
assert export('orders') == before
print('Orders report HTTP smoke passed: date/status filters, exact historical totals, full CSV snapshots, validation, separate export permissions and disable/re-enable persistence.')
