#!/usr/bin/env python3
import hashlib
import json
import re
from pathlib import Path
import sys
import zipfile

archive = sys.argv[1]
with zipfile.ZipFile(archive) as z:
    paths = set(z.namelist())
    assert 'manifest.json' in paths
    assert 'database.sql' in paths
    assert 'app-version.json' in paths
    assert any(s.startswith('modules_data/data/media/') for s in paths)
    assert any(s.startswith('themes_data/') for s in paths)
    assert not any(s.startswith('backup_data/') for s in paths)
    manifest = json.loads(z.read('manifest.json'))
    assert manifest['format'] == 'mcms-full-backup-v1'
    recorded = json.loads(z.read('app-version.json'))
    source = Path('src/Core/Version.php').read_text()
    current = re.search(r"public const VERSION = '([^']+)'", source)[1]
    assert recorded == {'application': 'mCMS', 'version': current}, recorded
    assert 'app-version.json' in manifest['files']
    assert set(manifest['files']) | {'manifest.json'} == paths
    assert b'CREATE TABLE' in z.read('database.sql')
    for path, expected in manifest['files'].items():
        content = z.read(path)
        assert expected['sha256'] == hashlib.sha256(content).hexdigest(), path
        assert expected['size'] == len(content), path
print('Complete site archive validated:',archive)
