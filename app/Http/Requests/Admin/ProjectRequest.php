<?php

namespace App\Http\Requests\Admin;

use App\Models\Person;
use App\Models\Project;
use App\Support\CompanyAccess;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CompanyAccess::check();
    }

    public function rules(): array
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        return [
            // A project must belong to a company - there is no loose project,
            // and it may only be one of the actor's own.
            'company_id' => ['required', $this->assignableCompany()],
            'name' => [
                'required', 'string', 'min:2', 'max:150',
                // Two companies may each run a "Phase 1"; one company may not.
                Rule::unique('projects', 'name')
                    ->where('company_id', $this->input('company_id'))
                    ->whereNull('deleted_at')
                    ->ignore($project?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'boolean'],

            // People assigned on the same form as the project itself.
            'people' => ['nullable', 'array'],
            'people.*' => ['integer', $this->assignablePerson()],
        ];
    }

    /**
     * A company the actor may file a project under. Null from allowedIds() is
     * an admin, who is restricted to nothing.
     */
    private function assignableCompany(): Exists
    {
        $rule = Rule::exists('companies', 'id')->whereNull('deleted_at');

        $allowed = CompanyAccess::allowedIds();

        // [0] rather than an empty IN (), which some drivers reject: a user
        // mapped to no company matches no company.
        if ($allowed !== null) {
            $rule->whereIn('id', $allowed ?: [0]);
        }

        return $rule;
    }

    /**
     * Someone the actor can actually see - so the same test the people list
     * uses, expressed as a rule.
     *
     * Not a Rule::exists: "visible" here means assigned to one of my companies
     * *or* not assigned anywhere yet, which is a condition on a relation
     * rather than on a column. Deferring to the model scope keeps the two
     * definitions from drifting apart.
     */
    private function assignablePerson(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $exists = Person::query()
                ->forCompanies(CompanyAccess::allowedIds())
                ->whereKey($value)
                ->exists();

            if (! $exists) {
                $fail('Please choose a person you have access to.');
            }
        };
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'Please choose the company this project belongs to.',
            // Covers both "no such company" and "not one of yours".
            'company_id.exists' => 'Please choose a company you have access to.',
            'name.unique' => 'This company already has a project with that name.',
            'end_date.after_or_equal' => 'The end date cannot be before the start date.',
        ];
    }

    public function attributes(): array
    {
        return [
            'company_id' => 'company',
            'people' => 'assigned people',
            'people.*' => 'person',
        ];
    }
}
