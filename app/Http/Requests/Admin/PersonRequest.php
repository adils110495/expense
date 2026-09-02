<?php

namespace App\Http\Requests\Admin;

use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
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

            // Loose on format, strict on content: numbers get written with
            // spaces, dashes and a +, and WhatsAppService normalises them at
            // send time. What matters is that there are enough digits.
            'whatsapp_number' => ['nullable', 'string', 'max:30', 'regex:/^[\d\s+()-]{10,}$/'],
            'whatsapp_enabled' => ['required', 'boolean'],
            'email_enabled' => ['required', 'boolean'],

            // Per-channel, per-type switches from the notification block.
            'prefs' => ['nullable', 'array'],
            'prefs.*' => ['array'],
            'prefs.*.*' => ['boolean'],

            'designation' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Project assignments, edited on the person form as well as on
            // the project form - either side may drive the pivot.
            'projects' => ['nullable', 'array'],
            'projects.*' => [Rule::exists('projects', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Another person already uses this email address.',
            'whatsapp_number.regex' => 'The WhatsApp number needs at least 10 digits.',
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
