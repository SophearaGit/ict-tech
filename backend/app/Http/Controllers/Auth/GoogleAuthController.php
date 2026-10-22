<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request)
    {
        $redirect = $request->query('redirect');
        if (is_string($redirect) && preg_match('#^/[\w\-/]*$#', $redirect)) {
            $request->session()->put('post_google_login_redirect', $redirect);
        } else {
            $request->session()->forget('post_google_login_redirect');
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        $redirect = $request->session()->pull('post_google_login_redirect');

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('account')->with('auth_error', 'Google sign-in failed. Please try again.');
        }

        $email = $googleUser->getEmail();

        if (! $email) {
            return redirect()->route('account')->with('auth_error', 'Your Google account has no email we can use.');
        }

        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->name = $googleUser->getName() ?: ($googleUser->getNickname() ?: 'Google User');
            $user->password = Str::random(40);
            $user->is_admin = false;
        }

        $user->email_verified_at ??= now();
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account', $redirect ? ['redirect' => $redirect] : []);
    }
}
