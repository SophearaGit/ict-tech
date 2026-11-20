<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Google sign-in needs three settings from the server's .env. When one is missing, Google would show its
 * own "Access blocked: Missing required parameter" page, so the shop says so itself and hides the button.
 */
class GoogleLoginSetupTest extends TestCase
{
    use RefreshDatabase;

    private const GOOGLE_HOST = 'accounts.google.com';

    private function googleSettings(array $overrides = []): void
    {
        config(['services.google' => $overrides + [
            'client_id' => 'test-client-id.apps.googleusercontent.com',
            'client_secret' => 'test-client-secret',
            'redirect' => 'https://shop.example.test/auth/google/callback',
        ]]);
    }

    /** @return array<string, array{0: string, 1: ?string}> */
    public static function missingSetting(): array
    {
        return [
            'no client id' => ['client_id', null],
            'an empty client id' => ['client_id', ''],
            'no client secret' => ['client_secret', null],
            'no redirect address' => ['redirect', null],
            'an empty redirect address' => ['redirect', ''],
        ];
    }

    #[DataProvider('missingSetting')]
    public function test_a_missing_setting_sends_the_visitor_back_with_a_clear_message_not_to_google(string $key, ?string $value): void
    {
        $this->googleSettings([$key => $value]);

        $response = $this->get(route('google.redirect'))
            ->assertRedirect(route('account'))
            ->assertSessionHas('auth_error');

        $this->assertStringNotContainsString(self::GOOGLE_HOST, (string) $response->headers->get('Location'));
        $this->assertStringContainsString('Google sign-in is not available', session('auth_error'));
    }

    public function test_a_missing_setting_is_written_to_the_log_by_name_never_by_value(): void
    {
        $this->googleSettings(['client_id' => null, 'client_secret' => 'super-secret-value']);

        $logged = '';
        \Illuminate\Support\Facades\Log::listen(function ($event) use (&$logged) {
            $logged .= $event->message.json_encode($event->context);
        });

        $this->get(route('google.redirect'));

        $this->assertStringContainsString('client_id', $logged);
        $this->assertStringNotContainsString('super-secret-value', $logged);
    }

    public function test_with_every_setting_present_the_visitor_goes_to_google_with_the_client_and_redirect_address(): void
    {
        $this->googleSettings();

        $location = (string) $this->get(route('google.redirect'))->assertRedirect()->headers->get('Location');

        $this->assertStringContainsString(self::GOOGLE_HOST, $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('test-client-id.apps.googleusercontent.com', $query['client_id']);
        $this->assertSame('https://shop.example.test/auth/google/callback', $query['redirect_uri']);
    }

    public function test_the_account_page_hides_the_google_button_until_google_is_set_up(): void
    {
        $this->googleSettings(['client_id' => null]);
        $this->get(route('account'))->assertOk()->assertSee('const googleReady = false;', false);

        $this->googleSettings();
        $this->get(route('account'))->assertOk()->assertSee('const googleReady = true;', false);
    }
}
