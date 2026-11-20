<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the first owner account from the server's .env (ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD).
 *
 * It looks first: when the server already has ANY admin (owner or staff, whatever email), nothing is
 * added and nothing is changed, so a deploy can never add a second owner or reset a password. Only a
 * server with no admin at all gets this account. With no usable settings (missing, shorter than 8
 * characters, or the "change-me" placeholder from .env.example) no account is made either, so a server
 * never ends up with a well-known login.
 */
class AdminSeeder extends Seeder
{
    private const PLACEHOLDER_PASSWORD = 'change-me';

    public function run(): void
    {
        // Search first: a server that already has an admin keeps exactly its own admins.
        if (Admin::exists()) {
            $this->command?->info('The server already has an admin, so no owner was added.');

            return;
        }

        $name = trim((string) config('shop.owner.name')) ?: 'Admin';
        $email = Str::lower(trim((string) config('shop.owner.email')));
        $password = (string) config('shop.owner.password');

        if ($email === '' || strlen($password) < 8 || $password === self::PLACEHOLDER_PASSWORD) {
            $this->command?->warn('No owner account was created: set ADMIN_EMAIL and a real ADMIN_PASSWORD (8 or more characters) in .env.');

            return;
        }

        $admin = new Admin(['name' => $name, 'email' => $email, 'password' => $password]); // the model hashes the password
        // `role` is not mass-assignable (see Admin): the account made here is the owner.
        $admin->forceFill(['role' => Admin::ROLE_OWNER])->save();
    }
}