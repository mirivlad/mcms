<?php
return [
'system'=>'Maintenance','title'=>'Updates','policy'=>'Automatic updates',
'help'=>'The background updater checks eligible releases. Manual updates and rollback remain available.',
'core'=>'mCMS Core','coreHint'=>'Stable patch releases only, after configuring the Portainer API.',
'modules'=>'Third-party modules','modulesHint'=>'Compatible updates with no new DB migrations or enabled dependents.',
'themes'=>'Third-party themes','themesHint'=>'Verified ZIP updates preserving the previous code version.',
'warning'=>'A full local backup is created before updates. This does not replace off-host backups! Database migrations and complex dependencies need manual review. Bundled modules and themes update with Core.',
'exceptions'=>'Exclude from automatic updates','none'=>'No catalog-managed external packages are installed.',
'save'=>'Save settings','check'=>'Extension catalog','lastCatalog'=>'Last check','never'=>'Never',
'pending'=>'Updates available','checkNow'=>'Check now','history'=>'Update history',
'lastRun'=>'Last run','noEvents'=>'No activity yet.',
];
