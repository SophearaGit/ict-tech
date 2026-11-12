<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/** The owner-only Admins API: who may call it, adding staff, switching them ON/OFF and resetting their password. */
class AdminAccountsTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private const NOT_OWNER = 'Only an owner can manage admins.';
    private const OWNER_TARGET = "Owner accounts can't be changed here.";
    private const EMAIL_TAKEN = 'That email is already used by another admin.';

    private ?Admin $owner = null;

    /** One owner per test, even when a test makes several requests. */
    private function actingAsOwner(): static
    {
        $this->owner ??= $this->makeAdmin();

        return $this->actingAs($this->owner, 'admin');
    }

    private function makeSecondOwner(): Admin
    {
        $admin = Admin::create(['name' => 'Second Owner', 'email' => 'owner2@example.test', 'password' => 'S3cret-pass!']);
        $admin->forceFill(['role' => Admin::ROLE_OWNER])->save();

        return $admin->fresh();
    }

    private function signIn(string $email, string $password): void
    {
        $this->post(route('admin-login.attempt'), ['email' => $email, 'password' => $password]);
    }

    private function credentialsWork(string $email, string $password): bool
    {
        return Auth::guard('admin')->validate(['email' => $email, 'password' => $password]);
    }

    private function newStaff(array $overrides = []): array
    {
        return $overrides + ['name' => 'Dara', 'email' => 'dara@example.test', 'password' => 'Pass-word-1'];
    }

    public function test_guest_is_redirected_to_the_login_page_on_every_admins_address(): void
    {
        $owner = $this->makeAdmin();

        $this->getJson(route('admin-admins.index'))->assertRedirect(route('admin-login'));
        $this->postJson(route('admin-admins.store'), $this->newStaff())->assertRedirect(route('admin-login'));
        $this->putJson(route('admin-admins.update', $owner), ['is_active' => false])->assertRedirect(route('admin-login'));

        $this->assertSame(1, Admin::count());
    }

    public function test_staff_are_refused_on_every_admins_address_and_nothing_changes(): void
    {
        $staff = $this->makeStaff();
        $other = $this->makeStaff('other@example.test');

        $this->actingAs($staff, 'admin');
        $this->getJson(route('admin-admins.index'))->assertForbidden()->assertJsonPath('message', self::NOT_OWNER);
        $this->postJson(route('admin-admins.store'), $this->newStaff())->assertForbidden()->assertJsonPath('message', self::NOT_OWNER);
        $this->putJson(route('admin-admins.update', $other), ['is_active' => false])->assertForbidden()->assertJsonPath('message', self::NOT_OWNER);

        $this->assertSame(2, Admin::count());
        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_index_lists_admins_oldest_first_with_the_exact_shape(): void
    {
        $owner = $this->makeAdmin();
        $staff = $this->makeStaff();
        $staff->forceFill(['last_login_at' => '2026-11-12 03:30:00'])->save(); // UTC; the shop shows Phnom Penh time (+7)

        $response = $this->actingAs($owner, 'admin')->getJson(route('admin-admins.index'))->assertOk();

        $this->assertSame([
            ['id' => $owner->id, 'name' => 'Test Admin', 'email' => 'owner@example.test', 'role' => 'owner', 'isActive' => true, 'lastLogin' => null, 'isYou' => true],
            ['id' => $staff->id, 'name' => 'Test Staff', 'email' => 'staff@example.test', 'role' => 'staff', 'isActive' => true, 'lastLogin' => '12 Nov 2026, 10:30', 'isYou' => false],
        ], $response->json());
    }

    public function test_the_owner_adds_a_staff_admin_with_a_trimmed_lower_case_email_and_a_hashed_password(): void
    {
        $response = $this->actingAsOwner()
            ->postJson(route('admin-admins.store'), $this->newStaff(['email' => '  Dara@Example.TEST ']))
            ->assertCreated();

        $admin = Admin::where('email', 'dara@example.test')->firstOrFail();
        $this->assertSame([
            'id' => $admin->id, 'name' => 'Dara', 'email' => 'dara@example.test', 'role' => 'staff', 'isActive' => true, 'lastLogin' => null, 'isYou' => false,
        ], $response->json());
        $this->assertSame('staff', $admin->role);
        $this->assertNotSame('Pass-word-1', $admin->password);
        $this->assertTrue(Hash::check('Pass-word-1', $admin->password));
    }

    public function test_a_create_request_cannot_make_an_owner(): void
    {
        $this->actingAsOwner()
            ->postJson(route('admin-admins.store'), $this->newStaff(['role' => 'owner', 'is_active' => false]))
            ->assertCreated()
            ->assertJsonPath('role', 'staff')
            ->assertJsonPath('isActive', true);

        $this->assertSame('staff', Admin::where('email', 'dara@example.test')->value('role'));
    }

    public function test_a_duplicate_email_is_refused_whatever_the_letter_case(): void
    {
        $this->actingAsOwner()->postJson(route('admin-admins.store'), $this->newStaff())->assertCreated();

        foreach (['dara@example.test', 'DARA@example.test', '  Dara@Example.Test  ', 'OWNER@example.test'] as $email) {
            $this->actingAsOwner()
                ->postJson(route('admin-admins.store'), $this->newStaff(['name' => 'Copy', 'email' => $email]))
                ->assertUnprocessable()
                ->assertJsonPath('errors.email.0', self::EMAIL_TAKEN);
        }

        $this->assertSame(2, Admin::count()); // the owner and Dara only
    }

    public function test_bad_input_is_refused_and_nothing_is_created(): void
    {
        $bad = [
            'an empty name' => [['name' => ''], 'name'],
            'a name of 61 characters' => [['name' => str_repeat('a', 61)], 'name'],
            'a missing name' => [['name' => null], 'name'],
            'an email without an at sign' => [['email' => 'not-an-email'], 'email'],
            'an email over 255 characters' => [['email' => str_repeat('a', 250).'@example.test'], 'email'],
            'a missing email' => [['email' => null], 'email'],
            'a 7 character password' => [['password' => 'Abcdef1'], 'password'],
            'a 73 character password' => [['password' => str_repeat('a', 73)], 'password'],
            'a missing password' => [['password' => null], 'password'],
        ];

        foreach ($bad as $label => [$override, $field]) {
            $this->actingAsOwner()
                ->postJson(route('admin-admins.store'), $this->newStaff($override))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field, responseKey: 'errors');
        }

        $this->assertSame(1, Admin::count());
    }

    public function test_password_length_limits_are_8_to_72_characters_and_khmer_letters_work(): void
    {
        $passwords = ['eight888' => 'a1@b2#c3', 'seventy2' => str_repeat('a', 72), 'khmer' => str_repeat('ក', 8)];

        foreach ($passwords as $name => $password) {
            $this->actingAsOwner()
                ->postJson(route('admin-admins.store'), $this->newStaff(['name' => $name, 'email' => "{$name}@example.test", 'password' => $password]))
                ->assertCreated();

            $this->assertTrue($this->credentialsWork("{$name}@example.test", $password), "{$name} should sign in");
        }

        // A name of exactly 60 characters is accepted (61 is refused in the bad-input test).
        $this->actingAsOwner()
            ->postJson(route('admin-admins.store'), $this->newStaff(['name' => str_repeat('n', 60), 'email' => 'sixty@example.test']))
            ->assertCreated();
    }

    public function test_a_new_staff_member_signs_in_and_can_use_everything_except_the_admins_page(): void
    {
        $this->actingAsOwner()->postJson(route('admin-admins.store'), $this->newStaff())->assertCreated();
        $this->post(route('admin-logout'));

        $this->signIn('dara@example.test', 'Pass-word-1');
        $this->assertAuthenticatedAs(Admin::where('email', 'dara@example.test')->firstOrFail(), 'admin');

        $this->get(route('admin-view'))->assertOk();
        $this->getJson(route('admin-products.index'))->assertOk();
        $this->getJson(route('admin-orders.index'))->assertOk();
        $this->getJson(route('admin-brands.index'))->assertOk();
        $this->getJson(route('admin-categories.index'))->assertOk();
        $this->getJson(route('admin-messages'))->assertOk();
        $this->getJson(route('admin-analytics.summary'))->assertOk();
        $this->getJson(route('admin-reports.sales', ['period' => 'week']))->assertOk();

        $this->getJson(route('admin-admins.index'))->assertForbidden();
        $this->postJson(route('admin-admins.store'), $this->newStaff(['email' => 'x@example.test']))->assertForbidden();
        $this->putJson(route('admin-admins.update', $this->owner), ['is_active' => false])->assertForbidden();
        $this->assertSame(2, Admin::count());
    }

    public function test_switching_a_staff_member_off_locks_them_out_at_once_and_on_restores_access(): void
    {
        $staff = $this->makeStaff();

        $this->actingAsOwner()->putJson(route('admin-admins.update', $staff), ['is_active' => false])
            ->assertOk()->assertJsonPath('isActive', false)->assertJsonPath('role', 'staff');
        $this->assertFalse($staff->fresh()->is_active);

        // A staff member who is already signed in is sent to the login page on the very next request
        // (a page load, and the page's own fetch calls, which treat that redirect as "session expired").
        $this->actingAs($staff->fresh(), 'admin')->get(route('admin-view'))->assertRedirect(route('admin-login'));
        $this->getJson(route('admin-products.index'))->assertRedirect(route('admin-login'));

        // ...and a fresh sign-in with the right password fails.
        $this->post(route('admin-logout'));
        $this->signIn('staff@example.test', 'S3cret-pass!');
        $this->assertGuest('admin');

        $this->actingAsOwner()->putJson(route('admin-admins.update', $staff), ['is_active' => true])->assertOk()->assertJsonPath('isActive', true);
        $this->post(route('admin-logout'));
        $this->signIn('staff@example.test', 'S3cret-pass!');
        $this->assertAuthenticatedAs($staff->fresh(), 'admin');
        $this->get(route('admin-view'))->assertOk();
    }

    public function test_resetting_a_password_makes_the_old_one_fail_and_the_new_one_work(): void
    {
        $staff = $this->makeStaff();

        $this->actingAsOwner()->putJson(route('admin-admins.update', $staff), ['password' => 'Brand-new-9'])->assertOk();

        $this->assertFalse($this->credentialsWork('staff@example.test', 'S3cret-pass!'));
        $this->assertTrue($this->credentialsWork('staff@example.test', 'Brand-new-9'));
        $this->assertTrue($staff->fresh()->is_active); // only the password changed
    }

    public function test_one_request_can_switch_off_and_reset_the_password_and_changes_nothing_else(): void
    {
        $staff = $this->makeStaff();

        $this->actingAsOwner()->putJson(route('admin-admins.update', $staff), [
            'is_active' => false, 'password' => 'Another-pass-1', 'name' => 'Hacked', 'email' => 'hacked@example.test', 'role' => 'owner',
        ])->assertOk();

        $staff = $staff->fresh();
        $this->assertFalse($staff->is_active);
        $this->assertTrue(Hash::check('Another-pass-1', $staff->password));
        $this->assertSame(['Test Staff', 'staff@example.test', 'staff'], [$staff->name, $staff->email, $staff->role]);
    }

    public function test_update_needs_a_valid_field_and_changes_nothing_when_refused(): void
    {
        $staff = $this->makeStaff();
        $hash = $staff->password;

        foreach ([[], ['is_active' => 'maybe'], ['password' => 'Abcdef1'], ['password' => str_repeat('a', 73)]] as $payload) {
            $this->actingAsOwner()->putJson(route('admin-admins.update', $staff), $payload)->assertUnprocessable();
        }

        $this->assertTrue($staff->fresh()->is_active);
        $this->assertSame($hash, $staff->fresh()->password);
    }

    public function test_an_owner_cannot_be_changed_here_not_even_by_themselves(): void
    {
        $second = $this->makeSecondOwner();
        $hash = $second->password;

        // Refused with 403 before the body is even looked at, so an empty or invalid body is still 403.
        foreach ([['is_active' => false], ['password' => 'Brand-new-9'], []] as $payload) {
            $this->actingAsOwner()->putJson(route('admin-admins.update', $second), $payload)
                ->assertForbidden()->assertJsonPath('message', self::OWNER_TARGET);
            $this->putJson(route('admin-admins.update', $this->owner), $payload)
                ->assertForbidden()->assertJsonPath('message', self::OWNER_TARGET);
        }

        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame($hash, $second->fresh()->password);
        $this->assertTrue($this->owner->fresh()->is_active);
        $this->assertTrue($this->credentialsWork('owner@example.test', 'S3cret-pass!'));
    }

    public function test_an_unknown_admin_is_not_found_and_there_is_no_delete(): void
    {
        $staff = $this->makeStaff();

        $this->actingAsOwner()->putJson(route('admin-admins.update', 9999), ['is_active' => false])->assertNotFound();
        $this->deleteJson('/admin/admins/'.$staff->id)->assertStatus(405);

        $this->assertSame(2, Admin::count());
    }
}
