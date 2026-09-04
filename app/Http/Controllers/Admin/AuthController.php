<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [], ['login' => 'email or username']);

        // Throttled per identifier+IP so a single account cannot be brute forced.
        $throttleKey = mb_strtolower($data['login']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'login' => 'Too many login attempts. Please try again in '
                    .RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        // The same box accepts either identifier; an "@" decides which column
        // to match so a username can never be checked against the email index.
        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // status => true keeps deactivated admins out even with a valid password.
        $attempted = Auth::guard('admin')->attempt([
            $field => $data['login'],
            'password' => $data['password'],
            'status' => true,
        ], $request->boolean('remember'));

        if (! $attempted) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'login' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // New session id on login - blocks session fixation.
        $request->session()->regenerate();

        UserActivity::record('login', 'admins', Auth::guard('admin')->id(), 'Signed in');

        return redirect()->intended(route('admin.dashboard'))
            ->with('success', 'Welcome back, '.Auth::guard('admin')->user()->name.'.');
    }

    /**
     * One logout for both doors.
     *
     * Admins and panel users share every screen, so they share the button that
     * signs them out; which guard is holding the session decides which one is
     * ended and which login screen they land back on.
     */
    public function logout(Request $request): RedirectResponse
    {
        $isAdmin = Auth::guard('admin')->check();
        $guard = $isAdmin ? 'admin' : 'web';

        // Logged before the guard forgets who it was.
        UserActivity::record('logout', $isAdmin ? 'admins' : 'users', Auth::guard($guard)->id(), 'Signed out');

        Auth::guard($guard)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($isAdmin ? 'admin.login' : 'user.login')
            ->with('success', 'You have been logged out.');
    }
}
