#!/usr/bin/env python3
"""Exercise per-material policies through real admin/public HTTP endpoints."""
import argparse
import html
import http.cookiejar
import re
import time
import urllib.error
import urllib.parse
import urllib.request

parser=argparse.ArgumentParser()
parser.add_argument('--base-url',default='http://127.0.0.1:18085')
parser.add_argument('--cookies',required=True)
args=parser.parse_args()
base=args.base_url.rstrip('/')
jar=http.cookiejar.MozillaCookieJar(args.cookies)
jar.load(ignore_discard=True,ignore_expires=True)
class LocalRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, message, headers, new_url):
        return super().redirect_request(request, fp, code, message, headers, urllib.parse.urldefrag(new_url)[0])
client=urllib.request.build_opener(LocalRedirect(), urllib.request.HTTPCookieProcessor(jar))
def get(path, actor=client):return actor.open(base+path).read().decode()
def post(path,fields,source,actor=client):
    csrf=html.unescape(re.search(r'name="csrf_test_name"[^>]*value="([^"]+)"',get(source,actor))[1])
    pairs=list(fields.items()) if isinstance(fields,dict) else fields
    response=actor.open(base+path,urllib.parse.urlencode(pairs+[('csrf_test_name',csrf)]).encode())
    return response.read().decode(),response.url
for module in ['comments','ratings','pages','news','catalogue','notifications']:
    post('/admin/modules/'+module+'/enable',{},'/admin/modules')
slug='policy-'+str(int(time.time()))
data={'title':'Interaction policy verification','slug':slug,'body':'<p>Local verification body</p>','status':'published','resource_options[comments]':'deny','resource_options[ratings]':'allow'}
body,url=post('/admin/pages/create',data,'/admin/pages/create')
match=re.search(r'/admin/pages/(\d+)/edit',url)
assert match,(url,body[-2000:])
id=match[1]
source='/admin/pages/'+id+'/edit'
assert 'value="deny" selected' in body and 'value="allow" selected' in body
assert 'id="comments"' not in get('/'+slug)
try:
    post('/comments/create',{'target_type':'pages.page','target_id':id,'body':'Direct bypass attempt'},source)
    raise AssertionError('Denied comment was accepted')
except urllib.error.HTTPError as error:assert error.code==404,error.code
post(source,{**data,'resource_options[comments]':'allow','resource_options[ratings]':'deny'},source)
assert 'id="comments"' in get('/'+slug)
try:
    post('/ratings/vote',{'target_type':'pages.page','target_id':id,'stars':'5'},source)
    raise AssertionError('Denied rating was accepted')
except urllib.error.HTTPError as error:assert error.code==404,error.code
post('/admin/modules/comments/disable',{},'/admin/modules')
assert 'resource_options[comments]' not in get(source)
post(source,{k:v for k,v in data.items() if k!='resource_options[comments]'},source)
post('/admin/modules/comments/enable',{},'/admin/modules')
assert 'value="allow" selected' in get(source)
# Make a thread for the browser checks using the actual moderation workflow.
post('/comments/create',{'target_type':'pages.page','target_id':id,'body':'First parent for thread'},source)
page=get('/admin/comments')
comment=re.search(r'/admin/comments/(\d+)/status',page)[1]
post('/admin/comments/'+comment+'/status',{'status':'published'},'/admin/comments')
post('/comments/create',{'target_type':'pages.page','target_id':id,'parent_id':comment,'body':'A nested reply for verification'},source)
page=get('/admin/comments')
reply=re.search(r'/admin/comments/(\d+)/status',page)[1]
post('/admin/comments/'+reply+'/status',{'status':'published'},'/admin/comments')
public=get('/'+slug)
assert '--comment-depth:1' in public and 'data-comment-reply' in public
# Editing interaction overrides follows content editing rights, not moderator rights.
name='policy.editor.'+str(time.time_ns())[-8:]
email=name+'@example.test'
users=post('/admin/users/create',{'username':name,'email':email,'password':'Policy-Editor-2026!'},'/admin/users/create')[0]
row=next(row for row in re.findall(r'<tr\b.*?</tr>',users,re.S) if name in row)
uid=re.search(r'/admin/users/(\d+)/access',row)[1]
access='/admin/users/'+uid+'/access'
post(access,[('permissions[]',value) for value in ['core.admin','pages.view','pages.edit']],access)
editor=urllib.request.build_opener(LocalRedirect(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
post('/login',{'email':email,'password':'Policy-Editor-2026!'},'/login',editor)
assert 'resource_options[comments]' in get(source,editor)
post(source,{**data,'resource_options[comments]':'allow','resource_options[ratings]':'deny'},source,editor)
assert 'value="deny" selected' in get(source)
post(access,[('permissions[]',value) for value in ['core.admin','pages.view']],access)
_,redirected=post(source,{**data,'resource_options[comments]':'deny'},'/account',editor)
assert redirected==base+'/'
assert 'id="comments"' in get('/'+slug)
jar.save(ignore_discard=True,ignore_expires=True)
print('Resource options HTTP smoke passed: save, direct POST denial, independent settings, module disable/re-enable and moderated thread.')
print('Browser resource: '+base+'/'+slug)
