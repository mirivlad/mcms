<?php
return [
    'title' => 'Catalogue settings', 'permission' => 'Manage module settings.',
    'status' => ['draft' => 'Draft', 'published' => 'Published'],
    'defaultStatus' => 'Initial status of new articles', 'defaultHint' => 'Applies to new editor forms; saving and publishing remain explicit actions.',
    'perPage' => 'Products per page',
    'menuStyle' => 'Category menu display',
    'menuStyleHint' => 'This tree is generated from published categories and updates automatically with the catalogue.',
    'style' => ['tree' => 'Full tree (default)', 'collapsed' => 'Collapsible tree (first level initially visible)', 'horizontal' => 'Horizontal dropdown'],
    'secondaryMenu' => 'Additional shop menu',
    'noSecondaryMenu' => 'No additional menu',
    'menusUnavailable' => 'Enable Menus to select a menu. Your existing assignment is preserved.',
    'secondaryScope' => 'Display additional menu on',
    'scope' => ['listing' => 'Catalogue and category pages', 'all_shop' => 'Catalogue, categories and product pages'],
    'expandCategories' => 'Expand subcategories',
    'pageHint' => 'Controls public listings; administration and existing content stay unchanged.',
    'invalid' => 'Choose a valid status and page size within the indicated limits.', 'saved' => 'Settings saved.',
];
