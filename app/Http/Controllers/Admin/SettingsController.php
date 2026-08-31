<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public const DATE_FORMATS = [
        'd M Y' => '28 Aug 2026',
        'd/m/Y' => '28/08/2026',
        'Y-m-d' => '2026-08-28',
        'm/d/Y' => '08/28/2026',
    ];

    public function index(): View
    {
        return view('admin.settings.index', [
            'admin' => auth('admin')->user(),
            'currencies' => Money::SYMBOLS,
            'dateFormats' => self::DATE_FORMATS,
            'settings' => Setting::all_settings(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'username' => [
                'required', 'string', 'min:3', 'max:50',
                // Letters, numbers, dot, underscore and hyphen only - an "@"
                // would make it ambiguous with an email at login.
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('admins', 'username')->ignore($admin->id),
            ],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($admin->id)],
        ], [
            'username.regex' => 'The username may only contain letters, numbers, dots, underscores and hyphens.',
        ]);

        $admin->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (! Hash::check($data['current_password'], $admin->password)) {
            return back()
                ->withErrors(['current_password' => 'Your current password is incorrect.'])
                ->with('error', 'Password not changed.');
        }

        $admin->update(['password' => $data['password']]);

        return back()->with('success', 'Password changed.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'currency' => ['required', Rule::in(array_keys(Money::SYMBOLS))],
            'date_format' => ['required', Rule::in(array_keys(self::DATE_FORMATS))],
        ]);

        foreach ($data as $key => $value) {
            Setting::put($key, $value);
        }

        return back()->with('success', 'Preferences saved.');
    }
}
