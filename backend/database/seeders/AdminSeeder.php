<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the first owner account from the server's .env (ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD).
 *
 * It only ever CREATES: when an admin with that email already exists, nothing about it is changed, so a
 * deploy can never reset a password. With no usable settings (missing, shorter than 8 characters, or the
 * "change-me" placeholder from .env.example) no account is made, so a server never ends up with a
 * well-known login.
 */
class AdminSeeder extends Seeder
{
    private const PLACEHOLDER_PASSWORD = 'change-me';

    public function run(): void
    {
        $name = trim((string) config('shop.owner.name')) ?: 'Admin';
        $email = Str::lower(trim((string) config('shop.owner.email')));
        $password = (string) config('shop.owner.password');

        if ($email === '' || strlen($password) < 8 || $password === self::PLACEHOLDER_PASSWORD) {
            $this->command?->warn('No owner account was created: set ADMIN_EMAIL and a real ADMIN_PASSWORD (8 or more characters) in .env.');

            return;
        }

        // Compared ignoring letter case, like the Admins page does, so "Boss@x.com" and "boss@x.com" are one address.
        if (Admin::whereRaw('lower(email) = ?', [$email])->exists()) {
            $this->command?->info('That owner email already exists, so it was left as it is.');

            return;
        }

        $admin = new Admin(['name' => $name, 'email' => $email, 'password' => $password]); // the model hashes the password
        // `role` is not mass-assignable (see Admin): the account made here is the owner.
        $admin->forceFill(['role' => Admin::ROLE_OWNER])->save();
    }
}
