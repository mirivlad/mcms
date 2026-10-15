<?php

return [
    'remove' => 'Remove', 'keep' => 'Remove code, keep data', 'purge' => 'Remove completely',
    'keepHint' => 'Keep database records, settings, files, migrations and rollback history. Reinstalling the same version is supported; modules remain disabled.',
    'purgeHint' => 'Complete removal also deletes the resources listed below and rollback history. Shared media and menus are kept. Make and verify a full backup first.',
    'blocked' => 'Removal is blocked by:', 'activeTheme' => 'the active theme (activate another theme first)',
    'backupRequired' => 'I have a current full database and files backup, saved outside the application volume.',
    'confirmRequired' => 'Confirm the mode and type the exact package ID.', 'typeId' => 'To confirm removal, type',
    'removed' => 'Extension removed.', 'code' => 'Code', 'tables' => 'Owned tables (complete removal only)',
    'settings' => 'Settings (complete removal only)', 'files' => 'Owned files (complete removal only)',
    'history' => 'History (kept in keep-data mode, deleted in complete mode)', 'recovery' => 'Extension removal recovery',
    'recoveryHint' => 'The operation continues forward from its journal. Fix storage/database access and retry. Public writes remain blocked while recovery is unfinished.',
    'retry' => 'Retry recovery', 'recovered' => 'Extension removal recovery completed.',
];
