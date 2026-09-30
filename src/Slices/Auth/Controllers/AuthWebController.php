<?php

namespace LaraSlice\Slices\Auth\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Routing\Controller;
use LaraSlice\Slices\Auth\Services\AuthSliceService;

class AuthWebController extends Controller
{
    protected AuthSliceService $authService;

    public function __construct(AuthSliceService $service)
    {
        $this->authService = $service;
    }

    /**
     * Display login page.
     */
    public function showLogin(): View
    {
        return view('auth::login');
    }

    /**
     * Process web login.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $remember = (bool) $request->input('remember', false);

        if ($this->authService->attemptWebLogin($credentials['email'], $credentials['password'], $remember)) {
            $request->session()->regenerate();
            return redirect()->intended('/laraslice/wizard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Process web logout.
     */
    public function logout(): RedirectResponse
    {
        $this->authService->logoutWeb();
        return redirect()->route('login')->with('success', 'You have been successfully logged out.');
    }
}
