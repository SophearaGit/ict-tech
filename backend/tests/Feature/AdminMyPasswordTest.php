<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/** "My password": any admin, owner or staff, changes their own password by giving the current one. */
class AdminMyPasswordTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private const WRONG_CURRENT = 'Your current password is not correct.';
    private const SAME_PASSWORD = 'The new password must be different from the current one.';

    /** @return array<string, Admin> the owner and a staff admin, both with the password S3cret-pass! */
    private function admins(): array
    {
        return ['owner' => $this->makeAdmin(), 'staff' => $this->makeStaff()];
    }

    private function change(Admin $admin, array $overrides = [])
    {
        return $this->actingAs($admin, 'admin')->putJson(route('admin-account.password'), $overrides + [
            'current_password' => 'S3cret-pass!',
            'password' => 'Brand-new-9',
            'password_confirmation' => 'Brand-new-9',
        ]);
    }

    private function credentialsWork(Admin $admin, string $password): bool
    {
        return Auth::guard('admin')->validate(['email' => $admin->email, 'password' => $password]);
    }

    private function signIn(Admin $admin, string $password): void
    {
        $this->post(route('admin-login.attempt'), ['email' => $admin->email, 'password' => $password]);
    }

    public function test_a_wrong_current_password_is_refused_and_nothing_changes(): void
    {
        foreach ($this->admins() as $admin) {
            $this->change($admin, ['current_password' => 'not-my-password'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.current_password.0', self::WRONG_CURRENT);

            $this->assertTrue($this->credentialsWork($admin, 'S3cret-pass!'));
            $this->assertFalse($this->credentialsWork($admin, 'Brand-new-9'));
        }
    }

    public function test_a_missing_current_or_new_password_is_refused(): void
    {
        foreach ($this->admins() as $admin) {
            $this->change($admin, ['current_password' => null])->assertUnprocessable()->assertJsonValidationErrors('current_password');
            $this->change($admin, ['password' => null, 'password_confirmation' => null])->assertUnprocessable()->assertJsonValidationErrors('password');
            $this->assertTrue($this->credentialsWork($admin, 'S3cret-pass!'));
        }
    }

    public function test_the_new_password_must_be_8_to_72_characters(): void
    {
        $admins = $this->admins();

        foreach ($admins as $admin) {
            foreach (['Abcdef1', str_repeat('a', 73)] as $tooBad) {
                $this->change($admin, ['password' => $tooBad, 'password_confirmation' => $tooBad])
                    ->assertUnprocessable()->assertJsonValidationErrors('password');
            }
            $this->assertTrue($this->credentialsWork($admin, 'S3cret-pass!'));
        }

        // The limits themselves are allowed: 8 for one admin, 72 for the other.
        $this->change($admins['owner'], ['password' => str_repeat('a', 72), 'password_confirmation' => str_repeat('a', 72)])->assertOk();
        $this->change($admins['staff'], ['password' => 'a1@b2#c3', 'password_confirmation' => 'a1@b2#c3'])->assertOk();
        $this->assertTrue($this->credentialsWork($admins['owner'], str_repeat('a', 72)));
        $this->assertTrue($this->credentialsWork($admins['staff'], 'a1@b2#c3'));
    }

    public function test_the_repeat_must_match_the_new_password(): void
    {
        foreach ($this->admins() as $admin) {
            $this->change($admin, ['password_confirmation' => 'Something-else-1'])
                ->assertUnprocessable()->assertJsonValidationErrors('password');
            $this->change($admin, ['password_confirmation' => null])
                ->assertUnprocessable()->assertJsonValidationErrors('password');

            $this->assertTrue($this->credentialsWork($admin, 'S3cret-pass!'));
        }
    }

    public function test_the_new_password_must_differ_from_the_current_one(): void
    {
        foreach ($this->admins() as $admin) {
            $this->change($admin, ['password' => 'S3cret-pass!', 'password_confirmation' => 'S3cret-pass!'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.password.0', self::SAME_PASSWORD);
        }
    }

    public function test_changing_the_password_works_and_keeps_the_admin_signed_in(): void
    {
        foreach ($this->admins() as $admin) {
            // A real sign-in first, so staying signed in afterwards is the session's doing and not the test helper's.
            $this->post(route('admin-logout'));
            $this->signIn($admin, 'S3cret-pass!');
            $this->assertAuthenticatedAs($admin, 'admin');

            $this->putJson(route('admin-account.password'), [
                'current_password' => 'S3cret-pass!', 'password' => 'Brand-new-9', 'password_confirmation' => 'Brand-new-9',
            ])->assertOk()->assertExactJson(['ok' => true]);

            $this->assertFalse($this->credentialsWork($admin, 'S3cret-pass!'));
            $this->assertTrue($this->credentialsWork($admin, 'Brand-new-9'));
            $this->get(route('admin-view'))->assertOk(); // still signed in

            // After signing out, the new password signs in and the old one does not.
            $this->post(route('admin-logout'));
            $this->signIn($admin, 'S3cret-pass!');
            $this->assertGuest('admin');
            $this->signIn($admin, 'Brand-new-9');
            $this->assertAuthenticatedAs($admin, 'admin');
        }
    }

    public function test_a_password_in_khmer_letters_can_be_set_and_used(): void
    {
        $khmer = str_repeat('ក', 8);

        foreach ($this->admins() as $admin) {
            $this->change($admin, ['password' => $khmer, 'password_confirmation' => $khmer])->assertOk();

            $this->assertTrue($this->credentialsWork($admin, $khmer));
        }
    }

    public function test_guests_and_switched_off_admins_are_sent_to_the_login_page(): void
    {
        $staff = $this->makeStaff();
        $payload = ['current_password' => 'S3cret-pass!', 'password' => 'Brand-new-9', 'password_confirmation' => 'Brand-new-9'];

        $this->putJson(route('admin-account.password'), $payload)->assertRedirect(route('admin-login'));

        $staff->forceFill(['is_active' => false])->save();
        $this->actingAs($staff->fresh(), 'admin')->putJson(route('admin-account.password'), $payload)->assertRedirect(route('admin-login'));

        $this->assertTrue($this->credentialsWork($staff, 'S3cret-pass!'));
        $this->assertFalse($this->credentialsWork($staff, 'Brand-new-9'));
    }
}
