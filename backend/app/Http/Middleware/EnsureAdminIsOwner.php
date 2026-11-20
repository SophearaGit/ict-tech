<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only the owner may manage other admins. Used inside the `admin` group, so a signed-out or switched-off
 * admin has already been sent to the login page; what reaches this point and is not an owner gets 403,
 * whatever address they type (hiding the menu item is not what protects it).
 */
class EnsureAdminIsOwner
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin || ! $admin->isOwner()) {
            abort(403, 'Only an owner can manage admins.');
        }

        return $next($request);
    }
}
