#!/usr/bin/env python3
"""Verify central settings and original module screens share handlers/state."""
import argparse
import html
import http.cookiejar
import re
import urllib.parse
import urllib.request
import uuid

parser=argparse.ArgumentParser()
parser.add_argument('--base-url', default='http://127.0.0.1:8080')
parser.add_argument('--cookies', required=True)
args=parser.parse_args()
base=args.base_url.rstrip('/')
jar=http.cookiejar.MozillaCookieJar(args.cookies)
jar.load(ignore_discard=True,ignore_expires=True)
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
def page(path):
    return client.open(base+path,timeout=60).read().decode()
def post(path,fields,form=None):
    source=page(form or path)
    csrf=html.unescape(re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"',source)[1])
    pairs=list(fields.items()) if isinstance(fields,dict) else list(fields)
    response=client.open(base+path,urllib.parse.urlencode(pairs+[('csrf_test_name',csrf)]).encode(),timeout=60)
    return response.read().decode(),response.url
for module in ['pages','seo','api','delivery']:
    post('/admin/modules/'+module+'/enable',{},'/admin/modules')
central=page('/admin/settings')
assert 'Settings centre' in central
for section in ['themes','media','pages','seo','delivery','api']:
    assert '/admin/settings/'+section in central,section
    assert 'Settings centre' in page('/admin/settings/'+section),section
    original={'themes':'/admin/themes','media':'/admin/media/settings','pages':'/admin/pages/settings','seo':'/admin/seo','delivery':'/admin/delivery','api':'/admin/api'}[section]
    assert 'Settings centre' not in page(original),original
# Writes through either entry point are visible at the other; success stays in its context.
source=page('/admin/settings/media')
upload=html.unescape(re.search(r'name="max_upload_mb"[\s\S]*?value="([^"]+)"',source)[1])
image=html.unescape(re.search(r'name="max_image_megapixels"[\s\S]*?value="([^"]+)"',source)[1])
allowed=re.findall(r'name="allowed_mime_types\[\]"\s+value="([^"]+)"\s+checked',source)
fields=[('max_upload_mb','9'),('max_image_megapixels',image)]+[('allowed_mime_types[]',value) for value in allowed]
_,url=post('/admin/settings/media',fields)
assert url.endswith('/admin/settings/media'),url
assert re.search(r'name="max_upload_mb"[\s\S]*?value="9"',page('/admin/media/settings'))
fields[0]=('max_upload_mb',upload)
_,url=post('/admin/media/settings',fields)
assert url.endswith('/admin/media/settings'),url
assert re.search(r'name="max_upload_mb"[\s\S]*?value="'+re.escape(upload)+'"',page('/admin/settings/media'))
# Theme controls and all form actions stay under central navigation.
source=page('/admin/settings/themes/mcms-default')
assert 'action="'+base+'/admin/settings/themes/mcms-default/appearance"' in source
assert base+'/admin/settings/themes/images' in source.replace('\\/', '/')
# Key creation uses exactly the same one-time-secret flow through the central alias.
source,url=post('/admin/settings/api/keys',[('name','Settings smoke '+uuid.uuid4().hex[:8]),('expires',''),('scopes[]','stock.read')],'/admin/settings/api')
assert url.endswith('/admin/settings/api') and 'id="api-secret"' in source
assert 'id="api-secret"' not in page('/admin/api')
key_id=re.search(r'/admin/settings/api/keys/(\d+)/revoke',source)[1]
_,url=post('/admin/settings/api/keys/'+key_id+'/revoke',{},'/admin/settings/api')
assert url.endswith('/admin/settings/api'),url
# Central Delivery uses the original module controller, including editing and redirects.
name='Settings pickup '+uuid.uuid4().hex[:8]
source,url=post('/admin/settings/delivery/create',{'name':name,'type':'pickup','price':'0','position':'5','enabled':'1'})
assert '/admin/settings/delivery/' in url and name in html.unescape(source), (url, name, re.findall(r'<div[^>]*alert[^>]*>.*?</div>', source, re.S))
assert name in page('/admin/delivery')
post('/admin/modules/delivery/disable',{},'/admin/modules')
assert '/admin/settings/delivery' not in page('/admin/settings')
post('/admin/modules/delivery/enable',{},'/admin/modules')
assert name in page('/admin/settings/delivery')
jar.save(ignore_discard=True,ignore_expires=True)
print('Central settings share original forms/state, preserve post-save navigation and follow module lifecycle.')
