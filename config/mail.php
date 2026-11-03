<?php

return [
    // MAIL_DRIVER is retained for existing stack environment files.
    'default' => env('MAIL_MAILER', env('MAIL_DRIVER', 'smtp')),
    'mailers' => [
        'mailgun' => ['transport' => 'mailgun', 'client' => ['timeout' => 30]],
        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME', env('MAIL_ENCRYPTION') === 'ssl' ? 'smtps' : 'smtp'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 587),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => 30,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],
        'sendmail' => ['transport' => 'sendmail', 'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i')],
        'ses' => ['transport' => 'ses'],
        'log' => ['transport' => 'log', 'channel' => env('MAIL_LOG_CHANNEL')],
        'array' => ['transport' => 'array'],
    ],
    'order_notification_address' => env('ORDER_NOTIFICATION_EMAIL', 'orders@trinityhouse.co.uk'),
    'test_recipient' => env('MAIL_TEST_RECIPIENT'),
    'admin_url' => env('ADMIN_URL', 'http://shopadmin.localhost:8080'),
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Shop'),
    ],
    'markdown' => ['theme' => 'default', 'paths' => [resource_path('views/vendor/mail')]],
];
