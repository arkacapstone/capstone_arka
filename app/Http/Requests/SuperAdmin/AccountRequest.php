<?php

namespace App\Http\Requests\SuperAdmin;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\AllowedAge;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the personal details of an Admin or Contractor account.
 * On update the account being edited is ignored by the unique email rule.
 */
class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole(UserRole::SuperAdmin, UserRole::Admin);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User|null $account */
        $account = $this->route('account');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($account)],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'birthday' => ['nullable', 'date', 'before:today', new AllowedAge],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'email',
            'phone_number' => 'phone number',
        ];
    }
}
