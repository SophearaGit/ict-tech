<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ValidatesAdminPasswords;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The owner-only API behind the Admins page: list the admins, add a staff admin, switch a staff admin
 * ON/OFF and set a new password for one. Only staff can be added or changed here; an owner can never be
 * edited on this page (so nobody can lock another owner out), and there is no delete (switch OFF instead).
 * Every route is behind the `owner` middleware.
 */
class AdminAccountController extends Controller
{
    use ValidatesAdminPasswords;

    private const EMAIL_TAKEN = 'That email is already used by another admin.';
    private const OWNER_TARGET = "Owner accounts can't be changed here.";

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Admin::orderBy('id')->get()->map(fn (Admin $admin) => $this->transform($admin, $request))
        );
    }

    /** Always creates a staff admin that is ON; the request can never choose the role. */
    public function store(Request $request): JsonResponse
    {
        // Emails are kept trimmed and lower-case, so "Dara@Example.TEST " and "dara@example.test" are one address.
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', $this->emailNotTaken()],
            'password' => ['required', ...$this->passwordRules()],
        ]);

        $admin = new Admin(['name' => $validated['name'], 'email' => $validated['email'], 'password' => $validated['password']]);
        $admin->forceFill(['role' => Admin::ROLE_STAFF])->save();

        // refresh(): a new row gets its column defaults (is_active = true) from the database, not from this object.
        return response()->json($this->transform($admin->refresh(), $request), 201);
    }

    /**
     * Switch a staff admin ON/OFF and/or set a new password for them (at least one). Name, email and role are
     * never touched. An owner target is refused before the body is looked at.
     */
    public function update(Request $request, Admin $admin): JsonResponse
    {
        if ($admin->isOwner()) {
            return response()->json(['message' => self::OWNER_TARGET], 403);
        }

        $request->validate([
            'is_active' => ['required_without:password', 'boolean'],
            'password' => ['required_without:is_active', ...$this->passwordRules()],
        ]);

        if ($request->has('is_active')) {
            $admin->is_active = $request->boolean('is_active');
        }
        if ($request->has('password')) {
            $admin->password = $request->input('password'); // the model hashes it
        }
        $admin->save();

        return response()->json($this->transform($admin, $request));
    }

    /** Taken ignoring letter case, checked with lower(email) so it behaves the same on MySQL and on the SQLite test database. */
    private function emailNotTaken(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (Admin::whereRaw('lower(email) = ?', [mb_strtolower((string) $value)])->exists()) {
                $fail(self::EMAIL_TAKEN);
            }
        };
    }

    private function transform(Admin $admin, Request $request): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $admin->role,
            'isActive' => $admin->is_active,
            // Shown in shop time (Phnom Penh), like the rest of the admin; null until the first sign-in.
            'lastLogin' => $admin->last_login_at?->copy()->timezone(config('shop.timezone'))->format('j M Y, H:i'),
            'isYou' => $admin->is($request->user('admin')),
        ];
    }
}
