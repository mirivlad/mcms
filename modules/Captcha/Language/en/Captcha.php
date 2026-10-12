<?php

return [
    'Admin' => ['name' => 'Captcha', 'description' => 'First-party single-use challenge protection for public forms.'],
    'Public' => [
        'question' => 'How many {1} {0} shapes are in the picture?',
        'answer' => 'Number of shapes',
        'newChallenge' => 'Get a new challenge',
        'invalid' => 'Incorrect challenge answer. Please try again.',
        'unavailable' => 'Challenge is temporarily unavailable. Try later.',
    ],
    'Shapes' => ['circle' => 'circle', 'square' => 'square', 'triangle' => 'triangle'],
    'Colors' => ['red' => 'red', 'blue' => 'blue', 'green' => 'green'],
];
