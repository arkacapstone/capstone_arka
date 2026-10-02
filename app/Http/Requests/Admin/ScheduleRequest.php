<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Enums\Weekday;
use App\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add / change schedule (Admin flow §IV). A change carries an effective date so the
 * previous schedule stays in history (Blueprint §6).
 */
class ScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole(UserRole::Admin, UserRole::SuperAdmin);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $changing = $this->route('schedule') !== null;

        return [
            'employee_id' => $changing
                ? ['prohibited']
                : ['required', 'integer', Rule::exists(User::class, 'id')->whereIn('role', UserRole::workforceValues())->where('status', 'active')],
            'client_id' => ['required', 'integer', Rule::exists(Client::class, 'id')->where('is_active', true)],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['distinct', Rule::enum(Weekday::class)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
            'break_allowance_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'start_date' => $changing ? ['prohibited'] : ['required', 'date'],
            'end_date' => ['nullable', 'date', $changing ? 'after_or_equal:effective_date' : 'after_or_equal:start_date'],
            'effective_date' => $changing ? ['required', 'date'] : ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'employee_id' => 'contractor',
            'client_id' => 'client',
            'working_days' => 'working days',
            'start_time' => 'start time',
            'end_time' => 'end time',
            'break_allowance_minutes' => 'break allowance',
            'effective_date' => 'effective date',
        ];
    }
}
