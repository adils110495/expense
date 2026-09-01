<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesHierarchy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Moving a batch of existing transactions onto a branch of the hierarchy.
 *
 * The hierarchy rules are the same ones a single expense has to satisfy, so a
 * bulk move cannot create a combination the add form would have refused.
 */
class BulkAssignRequest extends FormRequest
{
    use ValidatesHierarchy;

    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            // Not requireActive: these are older records being tidied up, and
            // an admin may well be filing them under a company or project that
            // has since been closed.
            ...$this->hierarchyRules(false),

            'transactions' => ['required', 'array', 'min:1'],
            'transactions.*' => [Rule::exists('transactions', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'transactions.required' => 'Select at least one transaction to assign.',
            ...$this->hierarchyMessages('transaction'),
        ];
    }

    public function attributes(): array
    {
        return [
            'company_id' => 'company',
            'project_id' => 'project',
            'person_id' => 'person',
            'transactions' => 'transactions',
        ];
    }
}
