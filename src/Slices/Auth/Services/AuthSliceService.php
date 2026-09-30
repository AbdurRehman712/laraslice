<?php

namespace LaraSlice\Slices\Auth\Services;

use LaraSlice\Core\Base\BaseSliceService;
use LaraSlice\Slices\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthSliceService
{
    /**
     * Web login attempt with session creation.
     */
    public function attemptWebLogin(string $email, string $password, bool $remember = false): bool
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return false;
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Your account is currently suspended or inactive.'],
            ]);
        }

        return Auth::attempt(['email' => $email, 'password' => $password], $remember);
    }

    /**
     * Mobile/Flutter API token issuance (Sanctum or bearer string fallback).
     */
    public function issueApiToken(string $email, string $password, string $deviceName = 'Flutter Client'): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Your account is currently suspended or inactive.'],
            ]);
        }

        // Support Laravel Sanctum if loaded, or standard secure token
        $token = method_exists($user, 'createToken')
            ? $user->createToken($deviceName)->plainTextToken
            : base64_encode(hash_hmac('sha256', $user->id . '|' . now()->timestamp, config('app.key', 'laraslice_secret_key')));

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'roles' => $user->roles->pluck('name')->toArray(),
            ]
        ];
    }

    /**
     * Register a new user account.
     */
    public function registerUser(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => 'active',
        ]);

        return $user;
    }

    /**
     * Logout web session.
     */
    public function logoutWeb(): void
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
