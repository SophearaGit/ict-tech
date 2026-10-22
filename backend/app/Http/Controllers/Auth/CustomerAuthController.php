<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CustomerAuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:4'],
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'error' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['ok' => true, 'user' => $this->publicUser($user), 'csrf_token' => csrf_token()]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'error' => $validator->errors()->first()], 422);
        }

        if (! Auth::attempt($validator->validated())) {
            return response()->json(['ok' => false, 'error' => 'Invalid email or password.'], 422);
        }

        $request->session()->regenerate();

        return response()->json(['ok' => true, 'user' => $this->publicUser(Auth::user()), 'csrf_token' => csrf_token()]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true, 'csrf_token' => csrf_token()]);
    }

    private function publicUser(User $user): array
    {
        return ['name' => $user->name, 'email' => $user->email];
    }
}
