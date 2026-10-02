<?php

namespace App\Http\Requests\SuperAdmin;

use App\Enums\PayFrequency;
use App\Models\Client;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pay terms for a client assignment. Only the Super Admin sets rates.
 * The client is chosen when assigning; a rate change keeps the same client.
 */
class RateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSuperAdmin();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $assigning = $this->route('rate') === null;

        return [
            'client_id' => $assigning
                ? ['required', 'integer', Rule::exists(Client::class, 'id')->where('is_active', true)]
                : ['prohibited'],
            'gross_pay' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'pay_frequency' => ['required', Rule::enum(PayFrequency::class)],
            'working_days' => ['required', 'integer', 'between:1,31'],
            'hours_per_day' => ['required', 'integer', 'between:1,24'],
            'effective_date' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'client_id' => 'client',
            'gross_pay' => 'gross pay',
            'pay_frequency' => 'pay frequency',
            'working_days' => 'working days',
            'hours_per_day' => 'hours per day',
            'effective_date' => 'effective date',
        ];
    }
}
