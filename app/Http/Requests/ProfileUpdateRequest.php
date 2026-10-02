<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\AllowedAge;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Profile & Security (Blueprint §3.4). Name, birthday, phone, address and emergency contact are the
 * user's own to edit; the login email is set by the organization and only the Super Admin may change theirs.
 */
class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'birthday' => ['nullable', 'date', 'before:today', new AllowedAge],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_number' => ['nullable', 'string', 'max:30'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }

    /**
     * The validated profile changes this user is allowed to make.
     *
     * @return array<string, mixed>
     */
    public function allowedChanges(): array
    {
        $changes = $this->validated();

        if (! $this->user()->isSuperAdmin()) {
            unset($changes['email']);
        }

        return $changes;
    }
}
