<?php

/*
 * The first Super Admin login, read by Database\Seeders\AdminSeeder. Kept in config (not read with env() in
 * the seeder) so it still works after `php artisan config:cache` on production.
 */
return [
    'name' => env('ADMIN_NAME', 'Super Admin'),
    'email' => env('ADMIN_EMAIL'),
    'password' => env('ADMIN_PASSWORD'),
];
