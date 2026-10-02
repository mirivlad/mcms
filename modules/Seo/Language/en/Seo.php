<?php

return [
    'Admin' => [
        'title' => 'SEO',
        'titleRule' => 'Title template',
        'descriptionRule' => 'Default description / template',
        'metadata' => 'Titles and metadata',
        'ruleHint' => 'Variables: {title} — content title, {site_name} — site name, {tags} — content tags. Manual editor title, description and keywords take priority. A selected Home page uses the Home rule.',
        'page' => 'Pages',
        'news' => 'News',
        'home' => 'Home',
        'keywords' => 'Default keywords / template',
        'keywordsSource' => 'Keyword source',
        'sourceManual' => 'From the field above',
        'sourceTags' => 'From content tags',

        'description' => 'Metadata rules, sitemap and redirects for moved content.',
        'sitemap' => 'Open sitemap',
        'redirectHint' => 'Old content addresses redirect to the current published resource. Reused addresses take precedence.',
        'source' => 'Old address',
        'target' => 'Current address',
        'empty' => 'No redirects yet.',
        'unavailable' => 'Resource is unavailable',
        'deleteConfirm' => 'Delete this redirect?',
    ],
    'Errors' => [
        'invalidRule' => 'Check field lengths and enter a title template.',
        'unknownToken' => 'Unknown template variable.',
        'invalidSource' => 'Choose a keyword source.',
    ],
    'Permissions' => [
        'manage' => 'Manage SEO metadata and redirects.',
    ],
    'Messages' => [
        'settingsSaved' => 'SEO settings saved.',
        'deleted' => 'Redirect deleted.',
    ],
];
