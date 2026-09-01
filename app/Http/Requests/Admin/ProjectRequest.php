<?php

namespace App\Http\Requests\Admin;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        return [
            // A project must belong to a company - there is no loose project.
            'company_id' => [
                'required',
                Rule::exists('companies', 'id')->whereNull('deleted_at'),
            ],
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
            'people.*' => [Rule::exists('people', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'Please choose the company this project belongs to.',
            'company_id.exists' => 'Please choose an existing company.',
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
