<?php

namespace App\Support;

/**
 * Whether "Continue with Google" can work. It needs three settings from the server's .env
 * (GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI, read through config/services.php).
 * With one missing, Google would answer with its own "Access blocked" error page, so the shop checks first.
 */
class GoogleLogin
{
    private const SETTINGS = ['client_id', 'client_secret', 'redirect'];

    /** The names (never the values) of the settings that are missing or empty. */
    public static function missingSettings(): array
    {
        return array_values(array_filter(
            self::SETTINGS,
            fn (string $key) => blank(config("services.google.{$key}")),
        ));
    }

    public static function isSetUp(): bool
    {
        return self::missingSettings() === [];
    }
}
