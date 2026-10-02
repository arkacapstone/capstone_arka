<?php

namespace App\Http\Requests\Auth;

use App\Rules\AllowedAge;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Personal details a new account owner fills in on first login. Work details
 * (ID, type, break allowance, clients and rates) stay with the Admin and Super Admin.
 */
class ProfileSetupRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', 'max:30'],
            'birthday' => ['required', 'date', 'before:today', new AllowedAge],
            'address' => ['required', 'string', 'max:255'],
            'emergency_contact_name' => ['required', 'string', 'max:255'],
            'emergency_contact_number' => ['required', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'phone_number' => 'phone number',
            'emergency_contact_name' => 'emergency contact name',
            'emergency_contact_number' => 'emergency contact number',
        ];
    }
}
