#!/usr/bin/env python3
"""Verify comment delivery/retry/moderation against a disposable loopback SMTP sink."""
import argparse
import email
import email.policy
import html
import http.cookiejar
import json
import re
import subprocess
import urllib.parse
import urllib.request
import uuid

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', default='http://127.0.0.1:18085')
parser.add_argument('--cookies', required=True)
parser.add_argument('--app-container', required=True)
args = parser.parse_args()
base = args.base_url.rstrip('/')
jar = http.cookiejar.MozillaCookieJar(args.cookies)
jar.load(ignore_discard=True, ignore_expires=True)


class LocalRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, message, headers, new_url):
        return super().redirect_request(request, fp, code, message, headers, urllib.parse.urldefrag(new_url)[0])


client = urllib.request.build_opener(LocalRedirect(), urllib.request.HTTPCookieProcessor(jar))
run_id = uuid.uuid4().hex[:8]
capture = '/tmp/comment-notifications-' + run_id + '.jsonl'


def page(path):
    return html.unescape(client.open(base + path, timeout=45).read().decode())


def post(path, fields, source):
    csrf = re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', page(source))[1]
    pairs = list(fields.items()) if isinstance(fields, dict) else list(fields)
    response = client.open(base + path, urllib.parse.urlencode(pairs + [('csrf_test_name', csrf)]).encode(), timeout=45)
    return html.unescape(response.read().decode())


def php(code):
    return subprocess.check_output(['docker', 'exec', args.app_container, 'php', '-r', code], text=True)


def messages():
    content = php('if (is_file(' + json.dumps(capture) + ')) {echo file_get_contents(' + json.dumps(capture) + ');}')
    return [email.message_from_string(json.loads(line)['message'], policy=email.policy.default) for line in content.splitlines()]


def failure(enabled):
    path = json.dumps(capture + '.fail')
    php('file_put_contents(' + path + ', "1");' if enabled else 'if (is_file(' + path + ')) {unlink(' + path + ');}')


probe = subprocess.Popen(['docker', 'exec', args.app_container, 'php', '.github/scripts/smtp-probe.php', capture], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
try:
    assert probe.stdout.readline().strip() == 'ready'
    post('/admin/comments/settings', [('allow_guests', '1'), ('max_depth', '3'), ('resources[]', 'pages.page'), ('resources[]', 'news.article'), ('resources[]', 'catalogue.product')], '/admin/comments/settings')
    config = [('enabled', '1'), ('recipient', 'moderator@example.test'), ('events[]', 'pending'), ('events[]', 'published')]
    post('/admin/notifications/comments', config, '/admin/notifications')
    page_id = re.search(r'/admin/pages/(\d+)/edit', page('/admin/pages'))[1]
    slug = re.search(r'name="slug"[^>]*value="([^"]+)"', page('/admin/pages/' + page_id + '/edit'))[1]

    def comment(suffix):
        body = 'SMTP comment ' + run_id + suffix
        result = post('/comments/create', {'target_type': 'pages.page', 'target_id': page_id, 'body': body}, '/' + slug)
        assert 'awaiting moderation' in result
        row = next(row for row in re.findall(r'<article\b.*?</article>', page('/admin/comments'), re.S) if body in row)
        return re.search(r'/admin/comments/(\d+)/status', row)[1]

    failure(True)
    first = comment('-first')
    assert messages() == []
    assert 'Mail delivery failed.' in page('/admin/notifications')
    failure(False)
    post('/admin/notifications/retry', {}, '/admin/notifications')
    sent = messages()
    assert len(sent) == 1 and sent[0]['To'] == 'moderator@example.test'
    assert 'awaiting moderation' in str(sent[0]['Subject'])
    assert '/admin/comments' in sent[0].get_content()
    post('/admin/comments/' + first + '/status', {'status': 'published'}, '/admin/comments')
    sent = messages()
    assert len(sent) == 2 and 'Comment published' in str(sent[1]['Subject'])
    assert '#comment-' + first in sent[1].get_content()
    post('/admin/comments/' + first + '/status', {'status': 'published'}, '/admin/comments')
    assert len(messages()) == 2
    failure(True)
    second = comment('-withdrawn')
    post('/admin/comments/' + second + '/status', {'status': 'published'}, '/admin/comments')
    post('/admin/comments/' + second + '/status', {'status': 'rejected'}, '/admin/comments')
    failure(False)
    post('/admin/notifications/retry', {}, '/admin/notifications')
    assert len(messages()) == 2, 'Rejected comment produced a delayed public alert'
    assert 'Cancelled' in page('/admin/notifications')
    result = subprocess.check_output(['docker', 'exec', args.app_container, 'php', 'spark', 'notifications:send'], text=True)
    assert 'Sent: 0; failed: 0' in result
    post('/admin/modules/notifications/disable', {}, '/admin/modules')
    comment('-notifications-disabled')
    post('/admin/modules/notifications/enable', {}, '/admin/modules')
    assert 'moderator@example.test' in page('/admin/notifications') and len(messages()) == 2
    post('/admin/notifications/comments', [field for field in config if field[0] != 'enabled'], '/admin/notifications')
    jar.save(ignore_discard=True, ignore_expires=True)
    print('Comment HTTP/SMTP smoke passed: moderation/publication snapshots, failed SMTP, retry, withdrawn alerts, no-op, CLI and disabled Notifications.')
finally:
    php('file_put_contents(' + json.dumps(capture + '.stop') + ', "1");')
    probe.communicate(timeout=10)
