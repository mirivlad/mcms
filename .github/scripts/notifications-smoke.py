#!/usr/bin/env python3
"""Exercise real order emails on a disposable stack with loopback SMTP on 2526."""
import argparse
import email
import email.policy
import html
import http.cookiejar
import json
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request
import uuid

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', default='http://127.0.0.1:8080')
parser.add_argument('--cookies', required=True)
parser.add_argument('--app-container', required=True)
parser.add_argument('--email', default='admin@example.test')
parser.add_argument('--password', default='Correct-Horse-Battery-Staple-42!')
args = parser.parse_args()
base = args.base_url.rstrip('/')
jar = http.cookiejar.MozillaCookieJar(args.cookies)
jar.load(ignore_discard=True, ignore_expires=True)
owner = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
guest = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
run_id = uuid.uuid4().hex[:8]
capture = '/tmp/notifications-' + run_id + '.jsonl'


def page(path, client=owner):
    response = client.open(base + path, timeout=45)
    return html.unescape(response.read().decode()), response.url


def post(path, fields, form=None, client=owner):
    source = page(form or path, client)[0]
    token = re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', source)[1]
    pairs = list(fields.items()) if isinstance(fields, dict) else list(fields)
    response = client.open(base + path, urllib.parse.urlencode(pairs + [('csrf_test_name', token)]).encode(), timeout=45)
    return html.unescape(response.read().decode()), response.url


def exec_php(code):
    return subprocess.check_output(['docker', 'exec', args.app_container, 'php', '-r', code], text=True)


def messages():
    rows = exec_php('echo file_get_contents(' + json.dumps(capture) + ');')
    return [email.message_from_string(json.loads(line)['message'], policy=email.policy.default) for line in rows.splitlines()]


probe = subprocess.Popen(['docker', 'exec', args.app_container, 'php', '.github/scripts/smtp-probe.php', capture], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
try:
    assert probe.stdout.readline().strip() == 'ready', 'SMTP probe did not start'
    if page('/login')[1] == base + '/login':
        post('/login', {'email': args.email, 'password': args.password})
    for module in ['notifications', 'delivery']:
        post('/admin/modules/' + module + '/enable', {}, '/admin/modules')
    assert '/admin/settings/notifications' in page('/admin/settings')[0]
    assert 'Settings centre' not in page('/admin/notifications')[0]
    config = [('enabled', '1'), ('recipient', 'manager@example.test'), ('events[]', 'new'), ('events[]', 'completed'),
              ('subject', 'Order {order_id}: {status}'), ('body', 'Buyer {customer_name}\n{phone}\n{delivery}\n{items}\nTotal {total}')]
    _, url = post('/admin/settings/notifications', config)
    assert url.endswith('/admin/settings/notifications'), url
    assert 'manager@example.test' in page('/admin/notifications')[0]
    post('/admin/settings/notifications', [('enabled','1'), ('recipient','wrong@example.test'), ('events[]','new'), ('subject','{unknown}'), ('body','bad')])
    assert 'manager@example.test' in page('/admin/notifications')[0], 'Invalid templates changed saved preferences'
    source, url = post('/admin/settings/notifications/test', {}, '/admin/settings/notifications')
    assert 'Test email sent.' in source and url.endswith('/admin/settings/notifications')
    assert len(messages()) == 1 and messages()[0]['To'] == 'manager@example.test'
    # Create independent stock and delivery fixtures through the same admin forms.
    slug = 'notification-' + run_id
    source = page('/admin/catalogue/create')[0]
    image_set = re.search(r'name="image_set_id" value="([^"]+)"', source)[1]
    source, url = post('/admin/catalogue/create', {'title': 'Mail coffee ' + run_id, 'slug': slug, 'summary':'', 'body':'', 'category_id':'', 'status':'published', 'image_set_id':image_set,
        'variants[0][id]':'0', 'variants[0][sku]':'MAIL-'+run_id, 'variants[0][label]':'Unit', 'variants[0][price]':'12.34'})
    product = re.search(r'/admin/catalogue/(\d+)/edit', url)[1]
    variant = re.search(r'name="variants\[0\]\[id\]"[^>]*value="(\d+)"', source)[1]
    post('/admin/stock/variant/' + variant, {'quantity':'5','note':'Notification fixture'}, '/admin/stock')
    source, url = post('/admin/delivery/create', {'name':'Mail pickup '+run_id,'type':'pickup','price':'0','position':'0','enabled':'1'})
    method = re.search(r'/admin/delivery/(\d+)$', url)[1]
    post('/cart/add', {'variant_id':variant,'quantity':'2','return':'/cart'}, '/shop/'+slug, guest)
    # Reject SMTP DATA. Checkout must still succeed and reserve stock.
    exec_php('file_put_contents(' + json.dumps(capture+'.fail') + ', "1");')
    source, _ = post('/order/checkout', {'name':'Mail Buyer '+run_id,'phone':'123','email':'buyer@example.test','delivery_method_id':method}, '/cart', guest)
    order = re.search(r'Order #(\d+) was created', source)[1]
    stock = page('/admin/stock?q=MAIL-'+run_id)[0]
    assert 'Reserved: 2' in stock and 'Available: 3' in stock, stock[-4000:]
    waiting = page('/admin/notifications')[0]
    assert '#'+order in waiting and 'Mail delivery failed.' in waiting and 'waiting: 1' in waiting
    assert len(messages()) == 1, 'Rejected SMTP data must not be treated as delivered'
    exec_php('unlink(' + json.dumps(capture+'.fail') + ');')
    source, _ = post('/admin/settings/notifications/retry', {}, '/admin/settings/notifications')
    assert 'Sent: 1; failed: 0.' in source
    sent = messages()
    assert len(sent) == 2
    assert sent[1]['To'] == 'manager@example.test' and 'buyer@example.test' not in str(sent[1]['To'])
    assert sent[1]['Subject'] == 'Order '+order+': New'
    body = sent[1].get_content()
    assert 'Mail Buyer '+run_id in body and 'Mail coffee '+run_id in body and '24.68 RUB' in body and 'Mail pickup '+run_id in body, body
    post('/admin/orders/'+order+'/status', {'status':'processing','note':''}, '/admin/orders/'+order)
    assert len(messages()) == 2, 'Unselected event sent mail'
    post('/admin/orders/'+order+'/status', {'status':'completed','note':''}, '/admin/orders/'+order)
    assert len(messages()) == 3 and messages()[2]['Subject'] == 'Order '+order+': Completed'
    post('/admin/orders/'+order+'/status', {'status':'completed','note':''}, '/admin/notifications')
    post('/admin/settings/notifications/retry', {}, '/admin/settings/notifications')
    assert len(messages()) == 3, 'No-op transition or retry duplicated a sent email'
    output = subprocess.check_output(['docker','exec',args.app_container,'php','spark','notifications:send'], text=True)
    assert 'Sent: 0; failed: 0' in output, output
    # Separate permission: an order reader cannot manage templates or send mail.
    username = 'mail.reader.'+run_id
    source, _ = post('/admin/users/create', {'username':username, 'email':username+'@example.test', 'password':'Mail-Reader-2026!'})
    row = next(row for row in re.findall(r'<tr\b.*?</tr>', source, re.S) if username in row)
    user_id = re.search(r'/admin/users/(\d+)/access', row)[1]
    access = '/admin/users/'+user_id+'/access'
    post(access, [('permissions[]','core.admin'),('permissions[]','core.settings'),('permissions[]','orders.view')])
    reader = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    post('/login', {'email':username+'@example.test','password':'Mail-Reader-2026!'}, client=reader)
    assert '/admin/settings/notifications' not in page('/admin/settings',reader)[0]
    assert page('/admin/notifications', reader)[1] == base+'/'
    assert post('/admin/settings/notifications/test', {}, '/admin/settings', reader)[1] == base+'/'
    assert post('/admin/settings/notifications', config, '/admin/settings', reader)[1] == base+'/'
    assert len(messages()) == 3, 'An order reader sent an unauthorized test email'
    post('/admin/modules/notifications/disable', {}, '/admin/modules')
    assert '/admin/settings/notifications' not in page('/admin/settings')[0]
    try:
        page('/admin/notifications')
        raise AssertionError('Disabled module route remains available')
    except urllib.error.HTTPError as error:
        assert error.code == 404
    post('/admin/modules/notifications/enable', {}, '/admin/modules')
    assert 'manager@example.test' in page('/admin/settings/notifications')[0]
    assert '#'+order in page('/admin/settings/notifications')[0]
    # Leave notification delivery switched off before restoring the normal SMTP host.
    post('/admin/notifications', [pair for pair in config if pair[0] != 'enabled'])
    jar.save(ignore_discard=True, ignore_expires=True)
    print('Notifications HTTP/SMTP smoke passed: templates, manager recipient, selected events, checkout during failure, retry, no-op, CLI, permissions and module lifecycle.')
finally:
    # Stop only this disposable loopback probe, never the application or mail service.
    exec_php('file_put_contents(' + json.dumps(capture+'.stop') + ', "1");')
    probe.communicate(timeout=10)
