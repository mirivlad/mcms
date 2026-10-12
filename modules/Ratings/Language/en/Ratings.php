<?php

return [
    'Admin' => [
        'title' => 'Ratings', 'description' => 'Five-star content ratings.',
        'settings' => 'Rating settings', 'saved' => 'Settings saved.',
        'allowGuests' => 'Allow guest votes (with captcha)',
        'resources' => 'Allowed content types', 'save' => 'Save',
        'votes' => 'Votes', 'source' => 'Content', 'delete' => 'Delete rating',
        'empty' => 'No votes yet.',
    ],
    'Public' => [
        'title' => 'Rating', 'average' => 'Average', 'votes' => 'votes',
        'myRating' => 'Your rating', 'submit' => 'Set rating',
        'login' => 'Sign in to rate this content.',
        'saved' => 'Rating saved.', 'invalid' => 'Unable to save rating.',
        'captchaUnavailable' => 'Challenge temporarily unavailable.',
    ],
    'Permissions' => ['view' => 'View ratings.', 'manage' => 'Manage ratings.'],
    'Errors' => ['invalid' => 'Invalid rating or content.', 'limit' => 'Too many requests.'],
];
