<?php

namespace App\Http\Controllers\Admin\Concerns;

/**
 * The one rule for an admin password, shared by the Admins page (add staff, reset) and "My password".
 * 72 characters is the most the hashing accepts.
 */
trait ValidatesAdminPasswords
{
    private function passwordRules(): array
    {
        return ['string', 'min:8', 'max:72'];
    }
}
