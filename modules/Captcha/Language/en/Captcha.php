<?php

return [
    'Admin' => ['name' => 'Captcha', 'description' => 'First-party single-use challenge protection for public forms.'],
    'Purposes' => ['auth_register' => 'Registration', 'feedback_contact' => 'Contact form', 'comments_create' => 'Comments', 'ratings_vote' => 'Ratings'],
    'Settings' => ['permission' => 'Manage CAPTCHA policies and statistics', 'difficulty' => 'Difficulty', 'easy' => 'Easy', 'standard' => 'Standard', 'hard' => 'Hard', 'ttl' => 'Token lifetime (seconds)', 'session_limit' => 'Challenges per session per minute', 'ip_limit' => 'Challenges per IP per minute', 'verify_limit' => 'Verification attempts per IP/session per minute', 'off' => 'Disabled', 'guests' => 'Guests only', 'all' => 'Everyone', 'saved' => 'CAPTCHA settings saved.', 'invalid' => 'Check CAPTCHA limits, difficulty and form policies.', 'statistics' => 'Checks in the last 7 days', 'form' => 'Form', 'issued' => 'Issued', 'passed' => 'Passed', 'failed' => 'Failed', 'expired' => 'Expired', 'limited' => 'Limited'],
    'Public' => [
        'confirmSend' => 'Verify and send',
        'confirmRating' => 'Verify and rate',
        'verification' => 'Verification',
        'addition' => 'What is {0} plus {1}?',
        'subtraction' => 'What is {0} minus {1}?',
        'visualAlternative' => 'Use the picture instead',
        'hint' => 'The text and picture give the same answer. One attempt; valid for {0} minutes. On expiry, submit the form to get a new task.',

        'question' => 'How many {1} {0} shapes are in the picture?',
        'answer' => 'Answer (whole number)',
        'newChallenge' => 'Get a new challenge',
        'required' => 'Please complete the verification to submit.',
        'invalid' => 'Incorrect challenge answer. Please try again.',
        'unavailable' => 'Challenge is temporarily unavailable. Try later.',
    ],
    'Shapes' => ['circle' => 'circle', 'square' => 'square', 'triangle' => 'triangle'],
    'Colors' => ['red' => 'red', 'blue' => 'blue', 'green' => 'green'],
];
