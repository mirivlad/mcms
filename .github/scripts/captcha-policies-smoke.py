#!/usr/bin/env python3
"""Run local CAPTCHA text/policy checks against a disposable installed site."""
import argparse
import html
import http.cookiejar
import re
import time
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


def actor(cookie_jar):
    return urllib.request.build_opener(LocalRedirect(), urllib.request.HTTPCookieProcessor(cookie_jar))


owner = actor(jar)
guest = actor(http.cookiejar.CookieJar())


def get(path, client=owner):
    response = client.open(base + path, timeout=30)
    return html.unescape(response.read().decode()), response.url


def submit(path, fields, page, client=owner):
    token = re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', page)[1]
    response = client.open(base + path, urllib.parse.urlencode({**fields, 'csrf_test_name': token}).encode(), timeout=30)
    return html.unescape(response.read().decode()), response.url


def solution(page):
    token = re.search(r'name="captcha_token" value="([a-f0-9]{48})"', page)[1]
    match = re.search(r'What is (\d+) (plus|minus) (\d+)\?', page)
    assert match, page[-2000:]
    answer = int(match[1]) + int(match[3]) if match[2] == 'plus' else int(match[1]) - int(match[3])
    return {'captcha_token': token, 'captcha_answer': str(answer)}


submit('/admin/modules/feedback/enable', {}, get('/admin/modules')[0])
settings = {'difficulty': 'easy', 'ttl': '300', 'session_limit': '120', 'ip_limit': '600', 'verify_limit': '600'}
settings.update({'policies[' + purpose + ']': 'guests' for purpose in ['auth.register', 'feedback.contact', 'comments.create', 'ratings.vote']})
submit('/admin/captcha', settings, get('/admin/captcha')[0])
assert 'captcha_token' not in get('/contact')[0], 'Guest policy challenged a signed-in visitor'
page = get('/contact', guest)[0]
assert 'aria-describedby=' in page and 'Use the picture instead' in page
fields = {'name': 'Local visitor', 'email': 'visitor@example.test', 'message': 'Accessible local contact verification'}
result = submit('/contact', {**fields, **solution(page)}, page, guest)[0]
assert 'Your message has been sent.' in result
# The same token cannot deliver another message.
result = submit('/contact', {**fields, **solution(page)}, get('/contact', guest)[0], guest)[0]
assert 'Incorrect challenge answer' in result
settings['policies[feedback.contact]'] = 'all'
submit('/admin/captcha', settings, get('/admin/captcha')[0])
page = get('/contact')[0]
assert 'captcha_token' in page
result = submit('/contact', fields, page)[0]
assert 'Incorrect challenge answer' in result
page = get('/contact')[0]
assert 'Your message has been sent.' in submit('/contact', {**fields, **solution(page)}, page)[0]
# Registration uses the same accessible task and never grants admin privileges.
page = get('/register', guest)[0]
name = 'captcha.visitor.' + str(time.time_ns())[-8:]
result, url = submit('/register', {'username': name, 'email': name + '@example.test', 'password': 'Correct-Horse-Battery-Staple-42!', 'password_confirm': 'Correct-Horse-Battery-Staple-42!', **solution(page)}, page, guest)
assert url == base + '/account', (url, result[-2000:])
assert get('/admin/captcha', guest)[1] == base + '/'
# Guests with CAPTCHA off are allowed by policy; restore guests-only afterwards.
settings['policies[feedback.contact]'] = 'off'
submit('/admin/captcha', settings, get('/admin/captcha')[0])
anonymous = actor(http.cookiejar.CookieJar())
assert 'captcha_token' not in get('/contact', anonymous)[0]
settings['policies[feedback.contact]'] = 'guests'
submit('/admin/captcha', settings, get('/admin/captcha')[0])
# Guests may post a comment only after solving their form-bound text task.
rows = get('/admin/pages')[0]
page_id = re.search(r'/admin/pages/(\d+)/edit', rows)[1]
edit = get('/admin/pages/' + page_id + '/edit')[0]
slug = re.search(r'name="slug"[^>]*value="([^"]+)"', edit)[1]
page = get('/' + slug, anonymous)[0]
result = submit('/comments/create', {'target_type': 'pages.page', 'target_id': page_id,
    'name': 'Accessible guest', 'body': 'A guest comment using the text challenge', **solution(page)}, page, anonymous)[0]
assert 'awaiting moderation' in result
# An IP bucket also limits newly rotated anonymous sessions.
settings['ip_limit'] = '8'
submit('/admin/captcha', settings, get('/admin/captcha')[0])
issued = 0
for _ in range(12):
    fresh = actor(http.cookiejar.CookieJar())
    issued += 'name="captcha_token"' in get('/contact', fresh)[0]
assert issued <= 8
settings['ip_limit'] = '600'
submit('/admin/captcha', settings, get('/admin/captcha')[0])
statistics = get('/admin/captcha')[0]
assert 'Checks in the last 7 days' in statistics and 'Passed' in statistics
jar.save(ignore_discard=True, ignore_expires=True)
print('CAPTCHA HTTP smoke passed: accessible text, contact/registration, replay denial, guests/all/off policies and admin permissions.')
