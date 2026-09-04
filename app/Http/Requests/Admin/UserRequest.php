<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validation for creating and editing a panel user, including the company
 * mapping that decides everything they will be able to see.
 *
 * Only an admin gets here - the routes carry admin.only - but authorize()
 * says so again rather than trusting the routing file to stay that way.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],

            // Required when creating; on an edit an empty box means "leave the
            // password alone", so the rule only applies when something is typed.
            'password' => [
                $this->isMethod('POST') ? 'required' : 'nullable',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],

            'status' => ['required', 'boolean'],

            // The mapping. An empty list is allowed and means the user can see
            // nothing yet - a real state an admin may want while setting
            // someone up, and one the dashboard explains rather than hides.
            'companies' => ['nullable', 'array'],
            'companies.*' => [
                'integer',
                Rule::exists('companies', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Another user already signs in with that email address.',
            'companies.*.exists' => 'One of the selected companies no longer exists.',
        ];
    }

    public function attributes(): array
    {
        return [
            'companies' => 'company mapping',
            'status' => 'status',
        ];
    }
}
