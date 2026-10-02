<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Services\ActivityLogger;
use App\Services\Settings\MailSettings;
use App\Services\Settings\SystemRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super Admin → System & Rules (Blueprint §3.1 module 8): the settings Payroll and the rest of
 * ARKA apply, plus the leave types contractors file against. Tithes and devotional penalties are
 * deliberately absent: they are never payroll deductions.
 */
class RuleController extends Controller
{
    /**
     * Section order and headings on the page. Notification settings live on the Notifications module.
     */
    public const GROUPS = [
        'payroll' => ['title' => 'Payroll rules', 'subtitle' => 'Defaults used when rates are created and pay is computed.'],
        'deductions' => ['title' => 'Administrative & device deductions', 'subtitle' => 'Which deductions payroll applies. Tithes and devotional penalties are never deducted.'],
        'cash_advances' => ['title' => 'Cash advance rules', 'subtitle' => 'The most a contractor can ask for. The full amount comes off their next payslip.'],
        'time_tracking' => ['title' => 'Time tracking', 'subtitle' => 'When contractors can start a timer. It stops by itself at the end of the shift; overtime goes through a ticket.'],
        'cutoff' => ['title' => 'Payroll cutoff & release', 'subtitle' => 'The semi-monthly cycle used to create new payroll periods.'],
        'system' => ['title' => 'Basic system configuration', 'subtitle' => 'General details shown across ARKA.'],
    ];

    public function index(SystemRules $rules, MailSettings $mail): Response
    {
        return Inertia::render('SuperAdmin/Rules/Index', [
            'groups' => $this->groups($rules, array_keys(self::GROUPS)),
            'mail' => [
                'username' => $mail->username() ?? '',
                'fromName' => $mail->fromName(),
                'hasPassword' => $mail->hasPassword(),
                'configured' => $mail->isConfigured(),
            ],
            'leaveTypes' => LeaveType::query()->withCount('requests')->orderByDesc('is_paid')->orderBy('id')->get()
                ->map(fn (LeaveType $type) => [
                    'id' => $type->id,
                    'name' => $type->leave_type_name,
                    'paid' => $type->is_paid,
                    'requests' => $type->requests_count,
                ])->all(),
        ]);
    }

    public function update(Request $request, SystemRules $rules, ActivityLogger $activity): RedirectResponse
    {
        $validated = $request->validate(self::validationRules($request->keys()));

        $first = $validated['first_cutoff_day'] ?? $rules->integer('first_cutoff_day');
        $second = $validated['second_cutoff_day'] ?? $rules->integer('second_cutoff_day');

        if ((int) $first >= (int) $second) {
            return back()->withErrors(['second_cutoff_day' => 'The second cutoff must come after the first cutoff.']);
        }

        $changed = $rules->update($validated, $request->user());

        if ($changed === []) {
            return back()->with('success', 'No changes to save.');
        }

        $activity->log('rules', 'Updated system rules', null, implode(', ', $changed));

        return back()->with('success', count($changed) === 1 ? "{$changed[0]} updated." : count($changed).' rules updated.');
    }

    public function storeLeaveType(Request $request, ActivityLogger $activity): RedirectResponse
    {
        $type = LeaveType::create($this->leaveTypeAttributes($request));
        $activity->log('rules', 'Added leave type', $type, $type->leave_type_name);

        return back()->with('success', "{$type->leave_type_name} added.");
    }

    public function updateLeaveType(Request $request, LeaveType $leaveType, ActivityLogger $activity): RedirectResponse
    {
        $leaveType->update($this->leaveTypeAttributes($request, $leaveType));
        $activity->log('rules', 'Updated leave type', $leaveType, $leaveType->leave_type_name);

        return back()->with('success', "{$leaveType->leave_type_name} updated.");
    }

    /**
     * Validation for the submitted rules only, built from each rule's definition.
     *
     * @param  list<string>  $keys
     * @return array<string, list<mixed>>
     */
    public static function validationRules(array $keys): array
    {
        $validation = [];

        foreach (SystemRules::definitions() as $name => $definition) {
            if (! in_array($name, $keys, true)) {
                continue;
            }

            $validation[$name] = match ($definition['type']) {
                'integer' => ['required', 'integer', 'min:'.$definition['min'], 'max:'.$definition['max']],
                'decimal' => ['required', 'numeric', 'min:'.$definition['min'], 'max:'.$definition['max']],
                'boolean' => ['required', 'boolean'],
                'time' => ['required', 'date_format:H:i'],
                default => ['required', 'string', 'max:255'],
            };
        }

        return $validation;
    }

    /**
     * @param  list<string>  $groups
     * @return list<array{key: string, title: string, subtitle: string, rules: list<array<string, mixed>>}>
     */
    public static function groups(SystemRules $rules, array $groups): array
    {
        $values = $rules->all();

        return array_map(fn (string $group) => [
            'key' => $group,
            'title' => self::GROUPS[$group]['title'] ?? str($group)->headline()->toString(),
            'subtitle' => self::GROUPS[$group]['subtitle'] ?? '',
            'rules' => array_values(array_map(
                fn (string $name) => [
                    'key' => $name,
                    'label' => SystemRules::definitions()[$name]['label'],
                    'type' => SystemRules::definitions()[$name]['type'],
                    'description' => SystemRules::definitions()[$name]['description'],
                    'min' => SystemRules::definitions()[$name]['min'] ?? null,
                    'max' => SystemRules::definitions()[$name]['max'] ?? null,
                    'value' => $values[$name],
                ],
                array_keys(array_filter(SystemRules::definitions(), fn (array $definition) => $definition['group'] === $group)),
            )),
        ], $groups);
    }

    /**
     * @return array{leave_type_name: string, is_paid: bool}
     */
    private function leaveTypeAttributes(Request $request, ?LeaveType $current = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique(LeaveType::class, 'leave_type_name')->ignore($current)],
            'paid' => ['required', 'boolean'],
        ]);

        return [
            'leave_type_name' => $validated['name'],
            'is_paid' => (bool) $validated['paid'],
        ];
    }
}
