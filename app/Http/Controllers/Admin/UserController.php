<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Company;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Panel users and their company mappings. Admin only - see EnsureAdmin.
 *
 * Nothing here is company-scoped: the whole point of the screen is to hand out
 * company access, which nobody working under a company restriction should be
 * able to do for themselves.
 *
 * Users are deactivated rather than deleted. The `users` table has no soft
 * deletes, so a delete would be irreversible, and status => false already
 * refuses the login outright.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->search($request->query('q'))
            ->inCompany($request->query('company_id'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (bool) $request->query('status')))
            // Eager loaded: the list prints each user's companies, and without
            // this that is one query per row.
            ->with('companies')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'companies' => Company::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.users.form', [
            'user' => new User(['status' => true]),
            'companies' => Company::active()->orderBy('name')->get(),
            // Pre-ticked when you arrive from a company's "add user" link.
            'mapped' => array_filter([$request->integer('company_id')]),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password', 'status']));

        $user->companies()->sync($request->input('companies', []));

        $this->logMapping($user, 'assigned');

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user,
            'companies' => Company::query()
                // Keep any company already mapped in the list even once it is
                // deactivated, so saving cannot silently revoke access.
                ->where(fn ($q) => $q->where('status', true)
                    ->orWhereHas('users', fn ($u) => $u->where('users.id', $user->id)))
                ->orderBy('name')
                ->get(),
            'mapped' => $user->companies()->pluck('companies.id')->all(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'email', 'status']);

        // An empty password box on an edit means "leave it alone" rather than
        // "set the password to nothing".
        if ($request->filled('password')) {
            $data['password'] = $request->input('password');
        }

        $user->update($data);

        // sync() both adds and removes in one go, so unticking a company is
        // what takes the access away - and it is gone on the user's very next
        // request, because nothing caches the mapping.
        $user->companies()->sync($request->input('companies', []));

        $this->logMapping($user, 'assigned');

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    /** Activate / deactivate. A deactivated user cannot sign in at all. */
    public function toggle(User $user): RedirectResponse
    {
        $user->update(['status' => ! $user->status]);

        return back()->with(
            'success',
            $user->name.' is now '.($user->status ? 'active' : 'inactive').'.',
        );
    }

    /**
     * Admin-set password reset. There is no self-service reset for users, so
     * this is how someone locked out gets back in.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user->update(['password' => $request->input('password')]);

        // Any remembered session on another device stops being valid.
        $user->forceFill(['remember_token' => null])->save();

        UserActivity::record('updated', 'users', $user->id, 'Password reset: '.$user->name);

        return back()->with('success', 'Password reset for '.$user->name.'.');
    }

    /**
     * The mapping change itself is worth an activity entry: LogsActivity sees
     * the model's own columns, but a pivot sync fires no model event, so it
     * would otherwise leave no trace of who was given access to what.
     */
    private function logMapping(User $user, string $action): void
    {
        $names = $user->companies()->pluck('companies.name')->implode(', ');

        UserActivity::record(
            $action,
            'user_company',
            $user->id,
            $user->name.' -> '.($names !== '' ? $names : 'no companies'),
        );
    }
}
