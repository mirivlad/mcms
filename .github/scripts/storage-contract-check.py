#!/usr/bin/env python3
"""Reject extension-specific persistent volumes in either Compose deployment."""
import json
import sys

model = json.load(sys.stdin)
declared = set(model['volumes'])
expected = {'db_data', 'modules_data', 'themes_data', 'mail_queue', 'dkim_data'}
assert declared == expected, f'Persistent volumes differ: {declared ^ expected}'

def mounts(service):
    return {v['source']: v for v in model['services'][service].get('volumes', [])}

app = mounts('app')
web = mounts('web')
assert set(app) == {'modules_data', 'themes_data'}, app
assert set(web) == {'modules_data'}, web
assert app['modules_data']['target'] == '/var/www/html/writable/modules-data'
assert app['themes_data']['target'] == '/var/www/html/writable/themes-data'
assert web['modules_data']['target'] == '/var/www/html/writable/modules-data'
assert web['modules_data']['read_only'] is True
assert app['modules_data'].get('read_only', False) is False
assert app['themes_data'].get('read_only', False) is False
assert set(mounts('db')) == {'db_data'}
assert set(mounts('mail')) == {'mail_queue', 'dkim_data'}
print('Shared-volume storage contract OK')
