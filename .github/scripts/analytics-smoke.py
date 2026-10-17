#!/usr/bin/env python3
"""Verify built-in analytics on a disposable, installed mCMS stack."""
import argparse
import datetime
import html
import http.cookiejar
import re
import secrets
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


def number(source, attribute, metric):
    return int(re.search(r'data-analytics-' + attribute + '="' + metric + r'">(\d+)<', source)[1])


post('/admin/modules/analytics/enable', {}, '/admin/modules')
before, _ = totals()
segment_query = '&path=%2F&source=search.example&device=desktop&prefix=/&group=hour'
segment_before = page(report_path + segment_query)[1]
guest = new_browser()
headers = {'User-Agent': browser_agent, 'Referer': 'https://search.example/private?q=secret'}
response, notice = page('/', guest, headers)
assert not any('mcms_analytics_visitor=' in cookie for cookie in (response.headers.get_all('Set-Cookie') or []))
assert 'analytics/privacy' in html.unescape(notice)
assert totals()[0] == before, 'Pageview recorded before explicit consent'
post('/analytics/privacy', {'choice': 'yes'}, '/analytics/privacy', guest)
response, _ = page('/?private_query=secret', guest, headers)
cookies = response.headers.get_all('Set-Cookie')
assert any('mcms_analytics_visitor=' in value and 'HttpOnly' in value and 'SameSite=Lax' in value for value in cookies)
assert 'private' in response.headers['Cache-Control'] and 'no-store' in response.headers['Cache-Control']
page('/', guest, {'User-Agent': browser_agent})
opted_browser = new_browser()
post('/analytics/privacy', {'choice': 'yes'}, '/analytics/privacy', opted_browser)
page('/', opted_browser, {'User-Agent': browser_agent, 'DNT': '1'})
page('/', opted_browser, {'User-Agent': browser_agent, 'Sec-GPC': '1'})
page('/', new_browser(), {'User-Agent': 'Googlebot'})
page('/', opted_browser, {'User-Agent': browser_agent, 'Purpose': 'prefetch'})
page('/login', opted_browser, {'User-Agent': browser_agent})
page('/', opted_browser, {'User-Agent': browser_agent})
after, source = totals()
assert {key: after[key] - before[key] for key in before} == {'views': 3, 'visits': 2, 'visitors': 2}, (before, after)
assert 'search.example' in source and 'private_query' not in source and '?q=secret' not in source
assert 'analytics-chart-title' in source and '<polyline' in source and 'Daily data' in source
assert 'data-analytics-comparison="available"' in source and 'data-period="previous"' in source
hourly = page(report_path + '&source=search.example&device=desktop&prefix=/&group=hour')[1]
assert 'data-group="hour"' in hourly and hourly.count('class="analytics-day"') == 24
assert number(hourly, 'total', 'views') == number(segment_before, 'total', 'views') + 2
details = page(report_path + '&path=%2F&source=search.example&device=desktop&group=hour')[1]
assert 'Home page' in details
assert number(details, 'page', 'entrances') == number(segment_before, 'page', 'entrances') + 1
assert number(details, 'page', 'exits') == number(segment_before, 'page', 'exits') + 1
assert 'name="path" value="/"' in html.unescape(details)
assert 'What and how' not in details and 'How these numbers are counted' not in details
assert 'data-analytics-comparison="unavailable"' in page('/admin/analytics?from=' + (datetime.date.fromisoformat(today) - datetime.timedelta(days=89)).isoformat() + '&to=' + today)[1]
for query in ['from=2026-02-30', 'from=bad', 'from=2026-01-01%00', 'from%5B%5D=2026-10-01', 'from=2000-01-01',
              'device=watch', 'source=%3Cscript%3E', 'prefix=https://example.com', 'path=%2F%3Fsecret', 'group=minute', 'compare%5B%5D=1']:
    try:
        page('/admin/analytics?' + query)
        raise AssertionError('Invalid analytics date accepted: ' + query)
    except urllib.error.HTTPError as error:
        assert error.code == 422, error.code

# A separate report permission controls both the sidebar and the direct route.
reader_name = 'analytics.reader.' + secrets.token_hex(3)
reader_email = reader_name + '@example.test'
source = post('/admin/users/create', {'username': reader_name, 'email': reader_email, 'password': 'Analytics-Reader-2026!'}, '/admin/users/create')
row = next(row for row in re.findall(r'<tr\b.*?</tr>', source, re.S) if reader_name in row)
reader_id = re.search(r'/admin/users/(\d+)/access', row)[1]
access = '/admin/users/' + reader_id + '/access'
post(access, [('permissions[]', 'core.admin')], access)
reader = new_browser()
post('/login', {'email': reader_email, 'password': 'Analytics-Reader-2026!'}, '/login', reader)
assert '/admin/analytics' not in page('/admin', reader)[1]
assert page(report_path, reader)[0].url == base + '/'
post(access, [('permissions[]', 'core.admin'), ('permissions[]', 'analytics.view')], access)
assert 'data-analytics-total="views"' in page(report_path, reader)[1]

# An explicit opt-out clears tracking cookies and prevents future page views.
post('/analytics/privacy', {'choice': 'no'}, '/analytics/privacy', guest)
response, _ = page('/', guest, {'User-Agent': browser_agent})
assert not any('mcms_analytics_visitor=' in value for value in (response.headers.get_all('Set-Cookie') or []))
assert totals()[0] == after, 'Pageview recorded after opting out'

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
try:
    page('/analytics/privacy')
    raise AssertionError('Disabled Analytics privacy route still works')
except urllib.error.HTTPError as error:
    assert error.code == 404
post('/admin/modules/analytics/enable', {}, '/admin/modules')
assert totals()[0] == saved
print('Analytics HTTP smoke passed: consent/opt-out, views/cookies, exclusions, comparison, combined filters, hourly charts, page details, validation, permissions and lifecycle persistence.')
