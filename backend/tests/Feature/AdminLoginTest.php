<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(bool $active = true): Admin
    {
        // fresh() so the model carries the database defaults (is_active), as a real login would
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'owner@example.test',
            'password' => 'S3cret-pass!',
        ])->fresh();

        if (! $active) {
            $admin->forceFill(['is_active' => false])->save();
        }

        return $admin;
    }

    private function loginAsAdmin(string $password = 'S3cret-pass!')
    {
        return $this->post(route('admin-login.attempt'), [
            'email' => 'owner@example.test',
            'password' => $password,
        ]);
    }

    public function test_admin_logs_in_and_lands_on_a_dashboard_that_shows_their_name(): void
    {
        $admin = $this->makeAdmin();

        $this->loginAsAdmin()->assertRedirect(route('admin-view'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->get(route('admin-view'))->assertOk()->assertSee('Test Admin');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->makeAdmin();

        $this->loginAsAdmin('not-the-password')->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_deactivated_admin_cannot_log_in_even_with_the_right_password(): void
    {
        $this->makeAdmin(active: false);

        $this->loginAsAdmin()->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_the_old_is_admin_flag_on_users_no_longer_grants_admin_login(): void
    {
        User::factory()->create([
            'email' => 'owner@example.test',
            'password' => 'S3cret-pass!',
            'is_admin' => true,
        ]);

        $this->loginAsAdmin()->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_login_records_when_the_admin_signed_in(): void
    {
        $admin = $this->makeAdmin();
        $this->travelTo('2026-03-01 10:00:00');

        $this->loginAsAdmin();

        $this->assertSame('2026-03-01 10:00:00', $admin->fresh()->last_login_at->toDateTimeString());
    }

    public function test_admin_login_does_not_also_sign_in_a_customer(): void
    {
        $this->makeAdmin();

        $this->loginAsAdmin()->assertRedirect(route('admin-view'));

        $this->assertGuest();
    }

    public function test_visitor_who_is_not_logged_in_is_sent_to_the_admin_login(): void
    {
        $this->get(route('admin-view'))->assertRedirect(route('admin-login'));
    }

    public function test_customer_account_flagged_is_admin_cannot_open_the_admin_area(): void
    {
        $customer = User::factory()->create(['is_admin' => true]);

        $this->actingAs($customer)->get(route('admin-view'))->assertRedirect(route('admin-login'));
    }

    public function test_admin_deactivated_while_logged_in_is_sent_back_to_the_login(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')->get(route('admin-view'))->assertOk();

        $admin->forceFill(['is_active' => false])->save();

        $this->get(route('admin-view'))->assertRedirect(route('admin-login'));
    }

    public function test_logout_ends_the_admin_session(): void
    {
        $this->makeAdmin();
        $this->loginAsAdmin();

        $this->post(route('admin-logout'))->assertRedirect(route('admin-login'));

        $this->assertGuest('admin');
        $this->get(route('admin-view'))->assertRedirect(route('admin-login'));
    }

    public function test_seeding_creates_the_admin_in_the_admins_table_not_in_users(): void
    {
        $this->seed(AdminSeeder::class);

        $this->assertSame(1, Admin::count());
        $this->assertTrue(Admin::first()->is_active);
        $this->assertSame(0, User::where('is_admin', true)->count());
    }
}
