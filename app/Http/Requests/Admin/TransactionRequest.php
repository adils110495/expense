<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesHierarchy;
use App\Models\Transaction;
use App\Support\CompanyAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for the single transaction form.
 *
 * Expenses and credits are one record and one form; the type is a field on it
 * rather than a property of the route, so the category rule reads the type off
 * the submission and demands a category of that same kind.
 */
class TransactionRequest extends FormRequest
{
    use ValidatesHierarchy;

    public function authorize(): bool
    {
        return CompanyAccess::check();
    }

    /**
     * The submitted type, or null when it is missing or not one of ours. Null
     * makes the category rule match nothing, which is the right outcome - the
     * separate `type` rule is what explains it to the user.
     */
    public function transactionType(): ?string
    {
        $type = $this->input('type');

        return in_array($type, Transaction::TYPES, true) ? $type : null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(Transaction::TYPES)],

            // Only creation demands an *active* company and project. Editing
            // an older record whose company was since deactivated must stay
            // possible, and the dropdowns already keep inactive entries out of
            // the everyday choice.
            ...$this->hierarchyRules($this->isMethod('POST')),

            'title' => ['required', 'string', 'min:2', 'max:150'],
            // gt:0 rejects zero and negatives; the regex pins it to 2 decimals.
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'regex:/^\d+(\.\d{1,2})?$/'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            // The category dropdown is refilled over AJAX when the type
            // changes, but this is the guard: a credit category can never be
            // saved onto an expense, however the form was driven.
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')
                    ->where('type', $this->transactionType())
                    ->where('status', true),
            ],
            'payment_method' => ['required', Rule::in(array_keys(Transaction::PAYMENT_METHODS))],

            // Shown as "Payment By" on expenses and "Payment Received" on
            // credits, but backed by the same list either way.
            'payment_by_id' => [
                'nullable',
                Rule::exists('payment_bys', 'id')->where('status', true),
            ],

            'location' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Receipts and invoices. Extensions are checked by content, not by
            // the filename, so a renamed executable cannot slip through.
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp,gif,pdf'],

            // Ids of existing attachments the user ticked for removal.
            'remove_attachments' => ['nullable', 'array'],
            'remove_attachments.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        $subject = $this->transactionType() ?? 'transaction';

        return [
            'type.required' => 'Please choose whether this is an expense or a credit.',
            'type.in' => 'Please choose whether this is an expense or a credit.',
            'amount.gt' => 'The amount must be greater than 0.',
            'amount.regex' => 'The amount may have at most 2 decimal places.',
            'category_id.exists' => 'Please choose an active '.$subject.' category.',
            'transaction_date.before_or_equal' => 'The date cannot be in the future.',
            'payment_by_id.exists' => 'Please choose an active entry from the list.',
            'attachments.max' => 'You can attach at most 10 files.',
            'attachments.*.max' => 'Each file must be 5 MB or smaller.',
            'attachments.*.mimes' => 'Attachments must be JPG, PNG, WEBP, GIF or PDF files.',

            ...$this->hierarchyMessages($subject),
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'transaction type',
            'category_id' => 'category',
            'transaction_date' => 'date',
            'payment_method' => 'payment method',
            'payment_by_id' => 'payment by',
            'company_id' => 'company',
            'project_id' => 'project',
            'person_id' => 'person',
        ];
    }
}
