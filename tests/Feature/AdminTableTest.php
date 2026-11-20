<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTableTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $overrides = []): Admin
    {
        return Admin::create($overrides + [
            'name' => 'Test Admin',
            'email' => 'owner@example.test',
            'password' => 'S3cret-pass!',
        ]);
    }

    public function test_admin_password_is_stored_hashed(): void
    {
        $this->makeAdmin();

        $stored = DB::table('admins')->where('email', 'owner@example.test')->value('password');

        $this->assertNotSame('S3cret-pass!', $stored);
        $this->assertTrue(Hash::check('S3cret-pass!', $stored));
    }

    public function test_admin_password_is_left_out_of_serialized_output(): void
    {
        $admin = $this->makeAdmin();

        $this->assertArrayNotHasKey('password', $admin->toArray());
    }

    public function test_new_admin_is_active_and_has_never_logged_in(): void
    {
        $admin = $this->makeAdmin()->fresh();

        $this->assertTrue($admin->is_active);
        $this->assertNull($admin->last_login_at);
    }

    public function test_two_admins_cannot_share_an_email(): void
    {
        $this->makeAdmin();

        $this->expectException(QueryException::class);

        $this->makeAdmin(['name' => 'Someone Else']);
    }
}
