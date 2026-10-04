#!/usr/bin/env python3
"""Check demo opt-in, actual public content, and no changes on repeat command runs."""
import argparse
import hashlib
import html
import http.cookiejar
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', default='http://127.0.0.1:8080')
parser.add_argument('--compose-project')
parser.add_argument('--install', action='store_true', help='Install only an isolated fresh test stack with demo opt-in already set')
args = parser.parse_args()
base = args.base_url.rstrip('/')
compose = ['docker', 'compose'] + (['-p', args.compose_project] if args.compose_project else [])


def command(flag):
    result = subprocess.run(compose + ['exec', '-T', 'app', 'env', 'MCMS_DEMO_SEED=' + flag, 'php', 'spark', 'mcms:demo:seed'],
                            check=True, capture_output=True, text=True)
    return result.stdout


def sql(query):
    result = subprocess.run(compose + ['exec', '-T', 'db', 'sh', '-c',
                            'exec mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" --batch --skip-column-names',
                            ], input=query, check=True, capture_output=True, text=True)
    return result.stdout.strip()


def snapshot():
    query = '\n'.join('SELECT * FROM ' + table + ' ORDER BY 1;' for table in
                      ['pages', 'news_articles', 'catalogue_products', 'catalogue_variants', 'catalogue_categories',
                       'product_image_sets', 'seo_redirects', 'settings', 'users'])
    return hashlib.sha256(sql(query).encode()).hexdigest()


opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
if args.install:
    source = opener.open(base + '/install').read().decode()
    csrf = html.unescape(re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"', source)[1])
    fields = {'site_name': 'Demo smoke', 'locale': 'ru', 'email': 'demo-smoke@example.test', 'username': 'demosmoke',
              'password': 'Local-Demo-Smoke-2026!', 'password_confirm': 'Local-Demo-Smoke-2026!', 'csrf_test_name': csrf}
    result = opener.open(base + '/install', urllib.parse.urlencode(fields).encode())
    assert result.url.endswith('/admin'), result.url
    assert sql("SELECT COUNT(*) FROM settings WHERE `key` = 'demoSeedCompletedAt';") == '1'

assert 'Demo data: disabled.' in command('0')
expected = 'already-completed' if args.install else 'completed'
assert 'Demo data: ' + expected + '.' in command('1')

public = urllib.request.build_opener()
for path, title in [('/demo-about', 'О проекте'), ('/demo-contacts', 'Контакты'),
                    ('/news/demo-opening', 'Добро пожаловать'), ('/shop/demo-ceramic-mug', 'Керамическая кружка'),
                    ('/shop/category/demo-home', 'Керамическая кружка')]:
    source = public.open(base + path).read().decode()
    assert title in source, path
    assert 'демо' in source.lower(), path
for path in ['/news/demo-draft', '/shop/demo-picnic-set']:
    try:
        public.open(base + path)
        raise AssertionError('A demo draft became public: ' + path)
    except urllib.error.HTTPError as error:
        assert error.code == 404


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


no_redirect = urllib.request.build_opener(NoRedirect())
for source, target in [('/demo-about-old.html', '/demo-about'), ('/demo-delivery-old.html', '/demo-delivery')]:
    try:
        no_redirect.open(base + source)
        raise AssertionError('Expected a 301 redirect: ' + source)
    except urllib.error.HTTPError as error:
        assert error.code == 301, (source, error.code)
        assert error.headers['Location'] == base + target

assert '/demo-about' in public.open(base + '/sitemap.xml').read().decode()
product = html.unescape(public.open(base + '/shop/demo-ceramic-mug').read().decode())
assert '350 мл' in product and '500 мл' in product and '890,00' in product
before = snapshot()
assert 'Demo data: already-completed.' in command('1')
assert before == snapshot(), 'A repeat run modified persistent data'
print('Demo HTTP smoke passed: opt-in, pages/news/products/categories, draft privacy, two 301 redirects and unchanged repeat-run snapshot.')
