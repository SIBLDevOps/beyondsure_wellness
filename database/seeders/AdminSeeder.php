<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the first Super Admin login. Safe to run on production, and safe to run again:
 *
 *  - It only CREATES the account. If an admin with that email already exists it is left exactly as it is,
 *    so a password someone changed is never reset.
 *  - The login comes from your .env, so nothing secret is written in the code:
 *
 *        ADMIN_NAME="Your Name"
 *        ADMIN_EMAIL=you@yourcompany.com
 *        ADMIN_PASSWORD=ChooseAStrongPass1
 *
 *  - No ADMIN_PASSWORD set: on a local machine the password is "password" for convenience; anywhere else a
 *    random password is generated and printed once. Change it afterwards from the profile menu.
 *
 *      php artisan db:seed --class=AdminSeeder
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) (config('admin.email') ?: (app()->environment('local') ? 'admin@beyondsure.test' : ''))));

        if ($email === '') {
            $this->command?->error('Set ADMIN_EMAIL (and ADMIN_PASSWORD) in .env, then run this seeder again.');

            return;
        }

        if (User::where('email', $email)->exists()) {
            $this->command?->warn("An account for {$email} already exists. Nothing was changed.");

            return;
        }

        $password = (string) config('admin.password', '');
        $generated = false;

        if ($password === '') {
            $generated = ! app()->environment('local');
            $password = $generated ? Str::password(14, symbols: false) : 'password';
        }

        User::create([
            'name' => (string) config('admin.name', 'Super Admin'),
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->command?->info("Super Admin created: {$email}");
        if ($generated) {
            $this->command?->warn("Password (shown once, change it after signing in): {$password}");
        }
    }
}
