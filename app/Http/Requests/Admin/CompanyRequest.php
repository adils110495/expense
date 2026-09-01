<?php

namespace App\Http\Requests\Admin;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        /** @var Company|null $company */
        $company = $this->route('company');

        return [
            'name' => [
                'required', 'string', 'min:2', 'max:150',
                // Unique among live companies only - a soft deleted one still
                // holds its old transactions but must not block the name.
                Rule::unique('companies', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($company?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A company with this name already exists.',
        ];
    }
}
