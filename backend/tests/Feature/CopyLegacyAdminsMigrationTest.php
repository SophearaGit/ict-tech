<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CopyLegacyAdminsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_10_27_150000_copy_legacy_admins_to_admins_table.php';

    /** RefreshDatabase has already migrated an empty database, so run the copy again with data in place. */
    private function runCopy(): void
    {
        (require database_path('migrations/'.self::MIGRATION))->up();
    }

    private function insertUser(string $name, string $email, string $passwordHash, bool $isAdmin): void
    {
        DB::table('users')->insert([
            'name' => $name,
            'email' => $email,
            'password' => $passwordHash,
            'is_admin' => $isAdmin,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_existing_admin_is_copied_with_the_exact_password_hash(): void
    {
        $hash = Hash::make('Old-pass-1');
        $this->insertUser('Shop Owner', 'owner@example.test', $hash, true);

        $this->runCopy();

        $admin = DB::table('admins')->where('email', 'owner@example.test')->first();
        $this->assertSame('Shop Owner', $admin->name);
        $this->assertSame($hash, $admin->password);
        $this->assertTrue(Hash::check('Old-pass-1', $admin->password));
    }

    public function test_customers_are_not_copied(): void
    {
        $this->insertUser('Plain Customer', 'customer@example.test', Hash::make('Cust-pass-1'), false);

        $this->runCopy();

        $this->assertDatabaseMissing('admins', ['email' => 'customer@example.test']);
    }

    public function test_running_the_copy_twice_does_not_duplicate_or_fail(): void
    {
        $this->insertUser('Shop Owner', 'owner@example.test', Hash::make('Old-pass-1'), true);

        $this->runCopy();
        $this->runCopy();

        $this->assertSame(1, DB::table('admins')->where('email', 'owner@example.test')->count());
    }
}
