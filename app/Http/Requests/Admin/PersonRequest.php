<?php

namespace App\Http\Requests\Admin;

use App\Models\Person;
use App\Support\CompanyAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class PersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CompanyAccess::check();
    }

    public function rules(): array
    {
        /** @var Person|null $person */
        $person = $this->route('person');

        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],

            // Optional, but two live people may not share one address.
            'email' => [
                'nullable', 'email', 'max:150',
                Rule::unique('people', 'email')
                    ->whereNull('deleted_at')
                    ->ignore($person?->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'designation' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Project assignments, edited on the person form as well as on
            // the project form - either side may drive the pivot.
            'projects' => ['nullable', 'array'],
            'projects.*' => [$this->assignableProject()],
        ];
    }

    /**
     * A project the actor may actually assign to.
     *
     * The form only lists their own companies' projects, but a hand-built POST
     * is not the form - without this, an id typed into the request would file
     * someone onto another company's project.
     */
    private function assignableProject(): Exists
    {
        $rule = Rule::exists('projects', 'id')->whereNull('deleted_at');

        $allowed = CompanyAccess::allowedIds();

        // Null is an admin: every company, so nothing to add. An empty array
        // is a user mapped to nothing, and [0] matches no company rather than
        // an empty IN () that some drivers reject outright.
        if ($allowed !== null) {
            $rule->whereIn('company_id', $allowed ?: [0]);
        }

        return $rule;
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Another person already uses this email address.',
        ];
    }

    public function attributes(): array
    {
        return [
            'projects' => 'assigned projects',
            'projects.*' => 'project',
        ];
    }
}
