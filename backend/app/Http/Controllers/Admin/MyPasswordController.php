<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ValidatesAdminPasswords;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "My password": any signed-in admin, owner or staff, changes their own password by giving the current one.
 * A session that is already open on another device stays open; to cut someone off, switch them OFF.
 */
class MyPasswordController extends Controller
{
    use ValidatesAdminPasswords;

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['bail', 'required', 'current_password:admin'],
            'password' => ['required', ...$this->passwordRules(), 'confirmed', 'different:current_password'],
        ], [
            'current_password.current_password' => 'Your current password is not correct.',
            'password.different' => 'The new password must be different from the current one.',
        ]);

        $request->user('admin')->update(['password' => $validated['password']]); // the model hashes it

        return response()->json(['ok' => true]);
    }
}
