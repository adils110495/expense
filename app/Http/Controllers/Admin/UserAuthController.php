<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserActivity;
use App\Support\CompanyAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sign-in for panel users, on the `web` guard.
 *
 * Deliberately a second door rather than a second panel: once through it a
 * user lands on the same dashboard an admin sees, with every query narrowed to
 * the companies mapped to them. Admins keep /admin/login and the `admin`
 * guard, so neither authentication system knows or affects the other.
 *
 * Users sign in with their email - unlike admins, they have no username
 * column, and adding one would mean changing the `users` table more than this
 * feature needs.
 */
class UserAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('admin.auth.user-login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // Throttled per identifier+IP so a single account cannot be brute
        // forced, exactly as the admin login is.
        $throttleKey = 'user|'.mb_strtolower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Please try again in '
                    .RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        // status => true keeps deactivated users out even with a valid
        // password, so switching someone off is a real lock rather than a
        // label on a list.
        $attempted = Auth::guard('web')->attempt([
            'email' => $data['email'],
            'password' => $data['password'],
            'status' => true,
        ], $request->boolean('remember'));

        if (! $attempted) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // New session id on login - blocks session fixation.
        $request->session()->regenerate();

        $user = Auth::guard('web')->user();

        UserActivity::record('login', 'users', $user->id, 'Signed in: '.$user->name);

        // A user mapped to nothing would land on an empty dashboard with no
        // way to tell why, so say it plainly instead.
        if ($user->allowedCompanyIds() === []) {
            return redirect()->route('admin.dashboard')->with(
                'error',
                'You are not mapped to any company yet. Ask an administrator to give you access.',
            );
        }

        return redirect()->intended(route('admin.dashboard'))
            ->with('success', 'Welcome back, '.$user->name.'.');
    }
}
