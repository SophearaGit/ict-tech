<?php

namespace Tests\Feature;

use App\Models\Admin;
use Database\Seeders\AdminSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** The owner/staff role on admins: its default, who can set it, and what the migration does to existing admins. */
class AdminRoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_11_12_100000_add_role_to_admins_table.php';

    private function migration(): Migration
    {
        return require database_path('migrations/'.self::MIGRATION);
    }

    private function insertAdmin(string $email, string $passwordHash): void
    {
        DB::table('admins')->insert([
            'name' => 'Someone',
            'email' => $email,
            'password' => $passwordHash,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_new_admin_is_staff_by_default(): void
    {
        $admin = Admin::create(['name' => 'New Person', 'email' => 'new@example.test', 'password' => 'S3cret-pass!'])->fresh();

        $this->assertSame('staff', $admin->role);
        $this->assertSame(Admin::ROLE_STAFF, $admin->role);
        $this->assertFalse($admin->isOwner());
    }

    public function test_the_role_cannot_be_set_by_mass_assignment(): void
    {
        $this->assertNull((new Admin(['name' => 'x', 'role' => 'owner']))->role);

        $admin = Admin::create(['name' => 'Sneaky', 'email' => 'sneaky@example.test', 'password' => 'S3cret-pass!', 'role' => 'owner'])->fresh();
        $this->assertSame('staff', $admin->role);
    }

    public function test_the_migration_makes_every_existing_admin_an_owner_and_keeps_their_password_hashes(): void
    {
        $migration = $this->migration();
        $migration->down(); // the column is gone, as on the live site before this update

        $first = Hash::make('Old-pass-1');
        $second = Hash::make('Old-pass-2');
        $this->insertAdmin('one@example.test', $first);
        $this->insertAdmin('two@example.test', $second);

        $migration->up();

        $this->assertSame(['owner', 'owner'], DB::table('admins')->orderBy('id')->pluck('role')->all());
        $this->assertSame([$first, $second], DB::table('admins')->orderBy('id')->pluck('password')->all());
        $this->assertSame(['one@example.test', 'two@example.test'], DB::table('admins')->orderBy('id')->pluck('email')->all());

        // An admin added after the update starts as staff, never as an owner.
        $this->insertAdmin('three@example.test', Hash::make('Old-pass-3'));
        $this->assertSame('staff', DB::table('admins')->where('email', 'three@example.test')->value('role'));
    }

    public function test_the_seeder_creates_an_owner(): void
    {
        $this->seed(AdminSeeder::class);

        $this->assertTrue(Admin::firstOrFail()->isOwner());
    }
}
