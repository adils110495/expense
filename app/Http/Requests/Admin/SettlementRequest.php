<?php

namespace App\Http\Requests\Admin;

use App\Models\Person;
use App\Models\Settlement;
use App\Support\CompanyAccess;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CompanyAccess::check();
    }

    public function rules(): array
    {
        /** @var Settlement|null $settlement */
        $settlement = $this->route('settlement');

        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'regex:/^\d+(\.\d{1,2})?$/'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'status' => ['required', Rule::in(array_keys(Settlement::STATUSES))],
            // Fixed once recorded - which side a payment clears is decided by
            // the list it was recorded from.
            'kind' => [$settlement ? 'nullable' : 'required', Rule::in(array_keys(Settlement::KINDS))],
            // How the money actually changed hands. Optional, because a
            // pending settlement has not been paid by any method yet.
            'payment_method' => ['nullable', Rule::in(array_keys(Settlement::PAYMENT_METHODS))],
            'settled_on' => ['nullable', 'date', 'before_or_equal:today'],
            'location' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Receipts, on the same rules as an expense: checked by content
            // rather than by filename, so a renamed executable cannot slip in.
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp,gif,pdf'],
            'remove_attachments' => ['nullable', 'array'],
            'remove_attachments.*' => ['integer'],

            // Only present when recording a new transfer; on update the two
            // partners are fixed.
            'from_person_id' => [
                $settlement ? 'nullable' : 'required',
                $this->visiblePerson(),
            ],
            'to_person_id' => [
                $settlement ? 'nullable' : 'required',
                'different:from_person_id',
                $this->visiblePerson(),
            ],
        ];
    }

    /**
     * A partner the actor can actually see.
     *
     * The settlement itself lands on their own project, so naming an outsider
     * would leak no money - but it would print that person's name on a page
     * they are not supposed to know about, and leave the settlement showing on
     * a stranger's record. Same test as the people list, so the two agree.
     */
    private function visiblePerson(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $visible = Person::query()
                ->whereNull('deleted_at')
                ->forCompanies(CompanyAccess::allowedIds())
                ->whereKey($value)
                ->exists();

            if (! $visible) {
                $fail('Please choose a partner you have access to.');
            }
        };
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Paying more than the transfer is worth would quietly push
                // the project past settled and invent a new debt the other way.
                if ($this->filled('paid_amount') && $this->filled('amount')
                    && bccomp($this->input('paid_amount'), $this->input('amount'), 2) === 1) {
                    $validator->errors()->add(
                        'paid_amount',
                        'The paid amount cannot be more than the settlement amount.'
                    );
                }

                if ($this->input('status') === 'partially_paid'
                    && (! $this->filled('paid_amount') || bccomp($this->input('paid_amount'), '0', 2) !== 1)) {
                    $validator->errors()->add(
                        'paid_amount',
                        'A partially paid settlement needs a paid amount greater than 0.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'amount.gt' => 'The amount must be greater than 0.',
            'amount.regex' => 'The amount may have at most 2 decimal places.',
            'to_person_id.different' => 'A partner cannot settle with themselves.',
            'settled_on.before_or_equal' => 'The settlement date cannot be in the future.',
            'attachments.max' => 'You can attach at most 10 files.',
            'attachments.*.max' => 'Each file must be 5 MB or smaller.',
            'attachments.*.mimes' => 'Attachments must be JPG, PNG, WEBP, GIF or PDF files.',
        ];
    }

    public function attributes(): array
    {
        return [
            'from_person_id' => 'paying partner',
            'to_person_id' => 'receiving partner',
            'paid_amount' => 'paid amount',
            'settled_on' => 'settlement date',
        ];
    }
}
