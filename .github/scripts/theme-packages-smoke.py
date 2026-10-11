#!/usr/bin/env python3
"""Exercise trusted theme installation, static assets and persisted settings."""
import argparse
import html
import http.cookiejar
import io
import json
import re
import time
import urllib.error
import urllib.parse
import urllib.request
import zipfile

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', default='http://127.0.0.1:8080')
parser.add_argument('--cookies', required=True)
parser.add_argument('--phase', choices=['install', 'persist'], default='install')
args = parser.parse_args()
base = args.base_url.rstrip('/')
jar = http.cookiejar.MozillaCookieJar(args.cookies)
jar.load(ignore_discard=True, ignore_expires=True)
owner = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
form = '/admin/themes/package'
manifest = {
    'id': 'theme-probe', 'name': 'Studio probe', 'version': '1.0.0',
    'namespace': 'MCMS\\Themes\\StudioProbe', 'mcms': '^0.1',
    'templates': {'home': 'home'}, 'screenshot': 'preview.svg',
    'menu_locations': {'header.primary': {'label': 'Main menu', 'max_depth': 2}},
    'home_defaults': {'title': 'Studio home'},
}
files = {
    'Views/home.php': '''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= esc($siteName) ?></title><link rel="stylesheet" href="<?= esc($themeAssets . 'css/style.css', 'attr') ?>"></head><body data-theme="theme-probe"><main><img src="<?= esc($themeAssets . 'preview.svg', 'attr') ?>" alt="Studio"><p class="eyebrow">External theme</p><h1><?= esc($themeHome['title']) ?></h1><p><?= esc($themeAppearance['phone']) ?></p><nav><?php foreach ($themeMenus['header.primary'] ?? [] as $item): ?><a href="<?= esc($item['url'], 'attr') ?>"><?= esc($item['label']) ?></a><?php endforeach ?></nav></main><script src="<?= esc($themeAssets . 'js/theme.js', 'attr') ?>"></script></body></html>''',
    'assets/css/style.css': '''@font-face{font-family:Studio;src:url(../fonts/studio.woff2)}*{box-sizing:border-box}body{margin:0;background:#f2f4f8;color:#263340;font:18px system-ui}main{max-width:800px;margin:6vh auto;padding:32px}img{width:80px}.eyebrow{color:#697889}h1{font-size:clamp(32px,6vw,64px);overflow-wrap:anywhere}a{color:#3150a2;margin-right:16px}''',
    'assets/js/theme.js': "document.body.dataset.scriptLoaded = 'yes';",
    'assets/fonts/studio.woff2': 'smoke-font-bytes',
    'assets/preview.svg': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80"><rect width="80" height="80" rx="16" fill="#3150a2"/><path d="M20 40h40M40 20v40" stroke="white" stroke-width="8"/></svg>',
}


def page(path, client=owner):
    response = client.open(base + path, timeout=30)
    return response.read().decode(), response.url


def csrf(path, client=owner):
    return html.unescape(re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', page(path, client)[0])[1])


def post(path, fields, source=form, client=owner):
    pairs = list(fields.items()) if isinstance(fields, dict) else fields
    response = client.open(base + path, urllib.parse.urlencode(pairs + [('csrf_test_name', csrf(source, client))]).encode(), timeout=30)
    return response.read().decode(), response.url


def archive(changes=None, extra=None):
    output = io.BytesIO()
    with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED) as package:
        package.writestr('theme.json', json.dumps({**manifest, **(changes or {})}))
        for name, content in {**files, **(extra or {})}.items():
            package.writestr(name, content)
    return output.getvalue()


def upload(content, client=owner, source=form):
    boundary = 'mcms-theme-smoke-boundary'
    payload = (f'--{boundary}\r\nContent-Disposition: form-data; name="csrf_test_name"\r\n\r\n{csrf(source, client)}\r\n'
               f'--{boundary}\r\nContent-Disposition: form-data; name="package"; filename="theme.zip"\r\n'
               'Content-Type: application/zip\r\n\r\n').encode() + content + f'\r\n--{boundary}--\r\n'.encode()
    response = client.open(urllib.request.Request(base + form, payload, {'Content-Type': 'multipart/form-data; boundary=' + boundary}), timeout=30)
    return response.read().decode(), response.url


def missing(path):
    try:
        page(path)
        raise AssertionError('Private/missing resource was exposed: ' + path)
    except urllib.error.HTTPError as error:
        assert error.code == 404, (path, error.code)


def assets():
    root = '/theme-assets/theme-probe/1.0.0/'
    for name, mime in [('css/style.css', 'text/css'), ('js/theme.js', 'text/javascript'), ('preview.svg', 'image/svg+xml'), ('fonts/studio.woff2', 'font/woff2')]:
        response = owner.open(base + root + name)
        assert response.read() == files['assets/' + name].encode()
        assert response.headers['Content-Type'].startswith(mime), response.headers
        assert response.headers['X-Content-Type-Options'] == 'nosniff'
        assert 'immutable' in response.headers['Cache-Control']
        if name.endswith('.svg'):
            assert 'sandbox' in response.headers['Content-Security-Policy']
        request = urllib.request.Request(base + root + name, headers={'If-None-Match': response.headers['ETag']})
        try:
            owner.open(request)
            raise AssertionError('ETag ignored')
        except urllib.error.HTTPError as error:
            assert error.code == 304 and error.read() == b''
    for path in ['theme.json', 'Views/home.php', '../theme.json', 'css/../../Views/home.php', 'missing.css', 'css/evil.php']:
        missing(root + path)
    missing('/theme-assets/theme-probe/2.0.0/css/style.css')
    missing('/theme-assets/mcms-default/0.4.1/css/theme.css')
    missing('/writable/themes-data/installed/theme-probe/Views/home.php')


if args.phase == 'persist':
    source = page('/')[0]
    assert 'data-theme="theme-probe"' in source and 'Saved studio title' in source and '+7 123 456' in source
    assert 'Studio probe' in page('/admin/themes')[0]
    assets()
    post('/admin/themes/mcms-default/activate', {}, '/admin/themes')
    assert 'data-theme="mcms-default"' in page('/')[0]
    post('/admin/themes/theme-probe/activate', {}, '/admin/themes')
    assert 'Saved studio title' in page('/')[0]
    # Return the stack to its built-in theme for subsequent unrelated smoke tests.
    post('/admin/themes/mcms-default/activate', {}, '/admin/themes')
    print('Theme views, assets, active selection and per-theme settings survived application recreation.')
else:
    assert form in page('/admin/themes')[0]
    # Pages may own Home after earlier content smoke; select the theme Home.
    try:
        page('/admin/pages/settings')
    except urllib.error.HTTPError as error:
        assert error.code == 404
    else:
        post('/admin/pages/settings', {'default_status': 'draft', 'home_page_id': '0'}, '/admin/pages/settings')
    post('/admin/themes/mcms-default/activate', {}, '/admin/themes')
    for content, message in [
        (archive(extra={'../outside.php': '<?php'}), 'Unsafe package path'),
        (archive({'mcms': '^99.0'}), 'requires mCMS'),
        (archive(extra={'Language/en/Studio.php': '<?php'}), 'Theme packages contain only'),
        (archive(extra={'assets/evil.php': '<?php'}), 'Theme packages contain only'),
        (archive({'templates': {'home': 'missing'}}), 'template is missing'),
    ]:
        assert message in upload(content)[0], message
    assert 'data-theme="mcms-default"' in page('/')[0]
    preview = upload(archive())[0]
    assert 'Theme package preview' in preview and 'SHA-256' in preview
    token = re.search(r'name="token" value="([^"]+)"', preview)[1]
    assert 'Confirm that you trust' in post(form + '/install', {'token': token})[0]
    assert 'Confirm that you trust' in post(form + '/install', {'token': '0' * 48, 'trusted': '1'})[0]
    # A user without core.themes cannot upload/install/cancel the owner's preview.
    reader_name = 'theme.reader.' + str(time.time_ns())[-8:]
    reader_email = reader_name + '@example.test'
    source = post('/admin/users/create', {'username': reader_name, 'email': reader_email, 'password': 'Theme-Reader-2026!'}, '/admin/users/create')[0]
    row = next(row for row in re.findall(r'<tr\b.*?</tr>', source, re.S) if reader_name in row)
    user_id = re.search(r'/admin/users/(\d+)/access', row)[1]
    access = '/admin/users/' + user_id + '/access'
    post(access, [('permissions[]', 'core.admin'), ('permissions[]', 'core.settings')], access)
    reader = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    post('/login', {'email': reader_email, 'password': 'Theme-Reader-2026!'}, '/login', reader)
    assert page(form, reader)[1] == base + '/'
    assert upload(archive(), reader, '/admin/settings')[1] == base + '/'
    for action in ['/install', '/cancel']:
        assert post(form + action, {'token': token, 'trusted': '1'}, '/admin/settings', reader)[1] == base + '/'
    assert token in page(form)[0]
    post(form + '/cancel', {})
    assert 'Theme package preview' not in page(form)[0]
    assert 'Confirm that you trust' in post(form + '/install', {'token': token, 'trusted': '1'})[0]
    preview = upload(archive())[0]
    token = re.search(r'name="token" value="([^"]+)"', preview)[1]
    installed = post(form + '/install', {'token': token, 'trusted': '1'})[0]
    assert 'Theme theme-probe installed.' in installed and 'Studio probe' in installed
    assert 'data-theme="mcms-default"' in page('/')[0]
    assert 'already exists' in upload(archive({'version': '2.0.0'}))[0]
    assets()
    config = '/admin/themes/theme-probe'
    post(config + '/home', {'home[title]': 'Saved studio title'}, config)
    post(config + '/appearance', {'appearance[phone]': '+7 123 456', 'appearance[accent]': '#3150a2'}, config)
    post(config + '/activate', {}, '/admin/themes')
    source = page('/')[0]
    assert 'data-theme="theme-probe"' in source and 'Saved studio title' in source and '+7 123 456' in source
    jar.save(ignore_discard=True, ignore_expires=True)
    print('Theme package validation, confirmation, permissions, inactive install, activation and static resource isolation passed.')
