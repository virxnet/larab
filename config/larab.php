<?php
return [
    'role_admin_super' => env('DFAULT_ROLE_ADMIN_EDITOR', 'AdminSuper'),
    'role_user' => env('DFAULT_ROLE_USER', 'ApiUser'),

    'user_admin_name' => env('DEFAULT_USER_ADMIN_NAME', 'admin'),
    'user_admin_first_name' => env('DEFAULT_USER_ADMIN_FIRST_NAME', 'Admin'),
    'user_admin_last_name' => env('DEFAULT_USER_ADMIN_LAST_NAME', 'Primary'),
    'user_admin_email' => env('DEFAULT_USER_ADMIN_EMAIL', 'admin@example.com'),
    'user_admin_password' => env('DEFAULT_USER_ADMIN_PASSWORD', 'password'),
];