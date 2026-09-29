<?php
return [
    'app_name' => 'RouteBox Telegram Bot',
    'timezone' => 'Asia/Tehran',
    'db' => __DIR__ . '/../storage/database.sqlite',
    'app_key' => '',
    'admin_user' => 'admin',
    'admin_password_hash' => '',
    'telegram' => ['token' => '', 'poll_timeout' => 25],
    'trial_hours' => 12,
    'security' => ['session_name' => 'rbt_session', 'cookie_secure' => false],
];
