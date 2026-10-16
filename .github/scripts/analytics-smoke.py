#!/usr/bin/env python3
"""Verify built-in analytics on a disposable, installed mCMS stack."""
import argparse
import datetime
import html
import http.cookiejar
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
today = datetime.datetime.now(datetime.timezone.utc).date().isoformat()
report_path = '/admin/analytics?' + urllib.parse.urlencode({'from': today, 'to': today})
browser_agent = 'Mozilla/5.0 (X11; Linux x86_64) Firefox/130.0'


def page(path, client=owner, headers=None):
    response = client.open(urllib.request.Request(base + path, headers=headers or {}), timeout=30)
    return response, response.read().decode()


def post(path, fields, form_path, client=owner):
    source = page(form_path, client)[1]
    csrf = html.unescape(re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', source)[1])
    pairs = list(fields.items()) if isinstance(fields, dict) else list(fields)
    response = client.open(base + path, urllib.parse.urlencode(pairs + [('csrf_test_name', csrf)]).encode(), timeout=30)
    return response.read().decode()


def totals():
    response, source = page(report_path)
    assert 'private' in response.headers['Cache-Control'] and 'no-store' in response.headers['Cache-Control']
    return {key: int(value) for key, value in re.findall(r'data-analytics-total="(views|visits|visitors)">(\d+)', source)}, source


def new_browser():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


post('/admin/modules/analytics/enable', {}, '/admin/modules')
before, _ = totals()
guest = new_browser()
headers = {'User-Agent': browser_agent, 'Referer': 'https://search.example/private?q=secret'}
response, _ = page('/?private_query=secret', guest, headers)
cookies = response.headers.get_all('Set-Cookie')
assert any('mcms_analytics_visitor=' in value and 'HttpOnly' in value and 'SameSite=Lax' in value for value in cookies)
assert 'private' in response.headers['Cache-Control'] and 'no-store' in response.headers['Cache-Control']
page('/', guest, {'User-Agent': browser_agent})
page('/', new_browser(), {'User-Agent': browser_agent, 'DNT': '1'})
page('/', new_browser(), {'User-Agent': browser_agent, 'Sec-GPC': '1'})
page('/', new_browser(), {'User-Agent': 'Googlebot'})
page('/', new_browser(), {'User-Agent': browser_agent, 'Purpose': 'prefetch'})
page('/login', new_browser(), {'User-Agent': browser_agent})
page('/', new_browser(), {'User-Agent': browser_agent})
after, source = totals()
assert {key: after[key] - before[key] for key in before} == {'views': 3, 'visits': 2, 'visitors': 2}, (before, after)
assert 'search.example' in source and 'private_query' not in source and '?q=secret' not in source
assert 'analytics-chart-title' in source and '<polyline' in source and 'Daily data' in source
for query in ['from=2026-02-30', 'from=bad', 'from=2026-01-01%00', 'from%5B%5D=2026-10-01', 'from=2000-01-01']:
    try:
        page('/admin/analytics?' + query)
        raise AssertionError('Invalid analytics date accepted: ' + query)
    except urllib.error.HTTPError as error:
        assert error.code == 422, error.code

# A separate report permission controls both the sidebar and the direct route.
source = post('/admin/users/create', {'username': 'analytics.reader', 'email': 'analytics-reader@example.test', 'password': 'Analytics-Reader-2026!'}, '/admin/users/create')
row = next(row for row in re.findall(r'<tr\b.*?</tr>', source, re.S) if 'analytics.reader' in row)
reader_id = re.search(r'/admin/users/(\d+)/access', row)[1]
access = '/admin/users/' + reader_id + '/access'
post(access, [('permissions[]', 'core.admin')], access)
reader = new_browser()
post('/login', {'email': 'analytics-reader@example.test', 'password': 'Analytics-Reader-2026!'}, '/login', reader)
assert '/admin/analytics' not in page('/admin', reader)[1]
assert page(report_path, reader)[0].url == base + '/'
post(access, [('permissions[]', 'core.admin'), ('permissions[]', 'analytics.view')], access)
assert 'data-analytics-total="views"' in page(report_path, reader)[1]

# Disabling removes collection and routes, and re-enable keeps prior statistics.
saved, _ = totals()
post('/admin/modules/analytics/disable', {}, '/admin/modules')
response, _ = page('/', guest, {'User-Agent': browser_agent})
assert not any('mcms_analytics_' in value for value in response.headers.get_all('Set-Cookie', []))
try:
    page(report_path)
    raise AssertionError('Disabled analytics route still works')
except urllib.error.HTTPError as error:
    assert error.code == 404
post('/admin/modules/analytics/enable', {}, '/admin/modules')
assert totals()[0] == saved
print('Analytics HTTP smoke passed: real views/cookies, deduplication, exclusions, private reports, date validation, permissions and disable/re-enable persistence.')
