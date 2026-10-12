<?php

return [
    'Admin' => [
        'title' => 'Comments', 'description' => 'Comments and a centralized moderation queue.',
        'empty' => 'No comments found', 'status' => 'Status', 'source' => 'Content',
        'moderate' => 'Moderation', 'reports' => 'Reports', 'delete' => 'Delete',
        'author' => 'Author', 'text' => 'Text', 'date' => 'Date',
        'settings' => 'Comment settings', 'save' => 'Save', 'saved' => 'Settings saved.',
        'allowGuests' => 'Allow guest comments (requires captcha)',
        'autoApprove' => 'Auto-publish comments by registered users',
        'maxDepth' => 'Maximum reply depth', 'resources' => 'Allowed content types',
    ],
    'Public' => [
        'title' => 'Comments', 'name' => 'Your name', 'email' => 'Email (not published)',
        'body' => 'Your comment', 'submit' => 'Post comment',
        'reply' => 'Reply', 'report' => 'Report', 'reason' => 'Reason for reporting',
        'pending' => 'Your comment is awaiting moderation.',
        'sent' => 'Your comment was published.', 'reported' => 'Report received.',
        'empty' => 'No comments yet.',
        'disabled' => 'Comments are disabled for this content.',
        'captchaUnavailable' => 'Challenge unavailable. Please try again later.',
        'login' => 'Sign in to comment.',
    ],
    'Status' => ['pending' => 'Pending', 'published' => 'Published', 'rejected' => 'Rejected', 'spam' => 'Spam', 'deleted' => 'Deleted'],
    'Permissions' => ['view' => 'View comments.', 'moderate' => 'Moderate comments.', 'delete' => 'Delete comments.'],
    'Errors' => ['invalid' => 'Invalid comment.', 'notFound' => 'Content unavailable.', 'parent' => 'Invalid reply parent.', 'limit' => 'Too many requests.'],
];
