<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Payroll\ManagePayrollPeriod;
use App\Enums\PayFrequency;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollStatus;
use App\Http\Controllers\Controller;
use App\Models\DeviceAssignment;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Services\Dashboard\Widgets\PayrollOverviewWidget;
use App\Services\Dashboard\Widgets\VerificationResultsWidget;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Payroll\PayrollPeriodResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super Admin → Payroll Management (Blueprint §11–§14). Payroll uses finalized attendance and
 * approved rules only; Admins and Contractors never see this module.
 */
class PayrollController extends Controller
{
    /**
     * Payroll Management, in three tabs: the current period's overview, every period, and who
     * verified their attendance (with what they fixed).
     */
    public function index(Request $request, PayrollPeriodResolver $resolver): Response
    {
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['overview', 'periods', 'verification'])],
            // The overview shows one pay frequency at a time; Semi-monthly is the default cycle.
            'frequency' => ['nullable', Rule::enum(PayFrequency::class)],
        ]);
        $tab = $filters['tab'] ?? 'overview';
        $frequency = PayFrequency::tryFrom($filters['frequency'] ?? '') ?? PayFrequency::SemiMonthly;

        $today = CarbonImmutable::today();
        $current = $resolver->currentFor($frequency, $today);
        $semiMonthly = $frequency === PayFrequency::SemiMonthly ? $current : $resolver->currentFor(PayFrequency::SemiMonthly, $today);

        // While the current period has no payroll yet, the overview shows the latest period of the same
        // frequency that does; the current period stays reachable (to open verification) above it.
        $latestWithPayroll = $current === null || ($current->exists && $current->payrolls()->exists())
            ? null
            : PayrollPeriod::query()->where('pay_frequency', $frequency)->has('payrolls')->orderByDesc('end_date')->orderByDesc('id')->first();
        $shown = $latestWithPayroll ?? $current;

        $periods = PayrollPeriod::query()
            ->withSum('payrolls', 'net_pay')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PayrollPeriod $period) => [
                'id' => $period->id,
                'name' => $period->period_name,
                'startDate' => $period->start_date->toDateString(),
                'endDate' => $period->end_date->toDateString(),
                'cutoffDate' => $period->cutoff_date->toDateString(),
                'releaseDate' => $period->release_date->toDateString(),
                'frequency' => $period->pay_frequency->label(),
                'frequencyValue' => $period->pay_frequency->value,
                'status' => $period->status->value,
                'statusLabel' => $period->status->label(),
                'net' => round((float) $period->payrolls_sum_net_pay, 2),
            ]);

        return Inertia::render('SuperAdmin/Payroll/Index', [
            'tab' => $tab,
            'frequency' => $frequency->value,
            // Null when no period of this frequency has been created yet.
            'current' => $shown ? (new PayrollOverviewWidget($shown, $today))->data() : null,
            'upcoming' => $latestWithPayroll ? (new PayrollOverviewWidget($current, $today))->data() : null,
            'periods' => $periods->all(),
            // View-only: the Admin already reviewed the contractors' changes.
            'verification' => (new VerificationResultsWidget(withChanges: false))->data(),
            'frequencies' => $this->frequencies(),
            // New period is pre-filled with the projected semi-monthly period while it isn't created yet.
            'suggested' => $semiMonthly->exists ? null : [
                'start_date' => $semiMonthly->start_date->toDateString(),
                'end_date' => $semiMonthly->end_date->toDateString(),
                'cutoff_date' => $semiMonthly->cutoff_date->toDateString(),
                'release_date' => $semiMonthly->release_date->toDateString(),
                'pay_frequency' => $semiMonthly->pay_frequency->value,
            ],
        ]);
    }

    public function store(Request $request, ManagePayrollPeriod $manage): RedirectResponse
    {
        $validated = $request->validate([
            'period_name' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'cutoff_date' => ['required', 'date', 'after_or_equal:start_date'],
            'release_date' => ['required', 'date', 'after_or_equal:cutoff_date'],
            'pay_frequency' => ['required', Rule::enum(PayFrequency::class)],
            'open_verification' => ['sometimes', 'boolean'],
        ]);

        $period = $manage->create(collect($validated)->except('open_verification')->all());

        // The dashboard can create the projected period and open verification in one step.
        if ($request->boolean('open_verification')) {
            $manage->advance($request->user(), $period);

            return back()->with('success', 'Attendance verification opened. Contractors have been notified.');
        }

        $response = to_route('super-admin.payroll.show', $period)->with('success', 'Payroll period created.');

        return ($gap = $this->gapBefore($period)) ? $response->with('warning', $gap) : $response;
    }

    public function show(PayrollPeriod $period): Response
    {
        $holdSuggestions = $this->holdSuggestions($period);

        $rows = $period->payrolls()
            ->with(['employee:id,name,employee_code,role', 'client:id,client_name', 'rate'])
            ->get()
            ->sortBy(fn (Payroll $row) => [$row->employee->name, $row->client?->client_name])
            ->values()
            ->map(fn (Payroll $row) => [
                'id' => $row->id,
                'employee' => ['name' => $row->employee->name, 'code' => $row->employee->employee_code],
                'client' => $row->client?->client_name,
                'gross' => (float) $row->gross_pay,
                'hourlyRate' => (float) $row->hourly_rate,
                'dailyRate' => (float) $row->daily_rate,
                'additionalHours' => (float) $row->additional_hours,
                'additionalTime' => intdiv($row->additional_minutes, 60).':'.str_pad((string) ($row->additional_minutes % 60), 2, '0', STR_PAD_LEFT),
                'lateTime' => intdiv($row->late_minutes, 60).':'.str_pad((string) ($row->late_minutes % 60), 2, '0', STR_PAD_LEFT),
                'additionalPay' => (float) $row->additional_pay,
                'overtime' => (float) $row->overtime_amount,
                'reward' => (float) $row->reward_amount,
                'daysAbsent' => (float) $row->days_absent,
                'absence' => (float) $row->absence_deduction,
                'lateHours' => (float) $row->late_hours,
                'late' => (float) $row->late_deduction,
                'cashAdvance' => (float) $row->cash_advance_deduction,
                'device' => (float) $row->device_deduction,
                'other' => (float) $row->other_deductions,
                'net' => (float) $row->net_pay,
                // Deductions above what the row earns; net pay stops at zero, so this is flagged for review.
                'shortfall' => PayrollCalculator::shortfall($row),
                'status' => $row->status->value,
                'heldAt' => $row->held_at?->toIso8601String(),
                'holdReason' => $row->hold_reason,
                'holdSuggestion' => $holdSuggestions[$row->employee_id] ?? null,
            ]);

        return Inertia::render('SuperAdmin/Payroll/Show', [
            'overview' => (new PayrollOverviewWidget($period, CarbonImmutable::today()))->data(),
            'rows' => $rows->all(),
            'canAdjust' => $period->status->allowsAdjustments(),
            'canHold' => in_array($period->status, [PayrollPeriodStatus::Locked, PayrollPeriodStatus::Processed, PayrollPeriodStatus::Released], true),
            'canDelete' => $period->status === PayrollPeriodStatus::Open,
            'employeesPaid' => PayrollCalculator::ratesFor($period)->distinct()->count('employee_id'),
            'verification' => [
                'verified' => $period->verifications()->whereNotNull('verified_at')->count(),
                'adminSubmittedAt' => $period->admin_submitted_at?->toIso8601String(),
                'adminSubmittedBy' => $period->adminSubmitter?->name,
            ],
        ]);
    }

    public function advance(Request $request, PayrollPeriod $period, ManagePayrollPeriod $manage): RedirectResponse
    {
        $from = $period->status;
        $manage->advance($request->user(), $period);

        return back()->with('success', match ($from) {
            PayrollPeriodStatus::Open => 'Attendance verification opened. Contractors have been notified.',
            PayrollPeriodStatus::Verification => 'Payroll processed: attendance locked and payroll calculated. Contractors have been notified.',
            PayrollPeriodStatus::Locked => 'Payroll approved.',
            default => 'Payslips released. Every contractor has been notified.',
        });
    }

    public function revert(PayrollPeriod $period, ManagePayrollPeriod $manage): RedirectResponse
    {
        $from = $period->status;
        $manage->revert($period);

        return back()->with('success', $from === PayrollPeriodStatus::Locked
            ? 'Attendance unlocked and draft payroll cleared. Contractors have been notified.'
            : 'Period moved back to open.');
    }

    public function adjust(Request $request, Payroll $payroll, ManagePayrollPeriod $manage): RedirectResponse
    {
        // Additional hours are worked out from attendance; the Super Admin may override them as a clock time (18:20). Days absent may be halves.
        $validated = $request->validate([
            'additional_time' => ['required', 'regex:/^\d{1,3}(:[0-5]\d)?$/'],
            'days_absent' => ['required', 'numeric', 'min:0', 'max:31', 'multiple_of:0.5'],
            'other_deductions' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ], [
            'additional_time.regex' => 'Enter additional hours as hours:minutes, e.g. 18:20.',
        ], ['additional_time' => 'additional hours', 'days_absent' => 'days absent']);

        [$hours, $minutes] = array_pad(explode(':', $validated['additional_time']), 2, '0');

        $manage->adjust($payroll, [
            'additional_minutes' => (int) $hours * 60 + (int) $minutes,
            'days_absent' => $validated['days_absent'],
            'other_deductions' => $validated['other_deductions'],
        ]);

        return back()->with('success', 'Payroll row updated.');
    }

    public function hold(Request $request, Payroll $payroll, ManagePayrollPeriod $manage): RedirectResponse
    {
        $validated = $request->validate(['hold_reason' => ['required', 'string', 'max:255']], attributes: ['hold_reason' => 'reason']);

        $manage->hold($payroll, $validated['hold_reason']);

        return back()->with('success', 'Payroll on hold. It stays out of the release until you lift the hold.');
    }

    public function releaseHold(Payroll $payroll, ManagePayrollPeriod $manage): RedirectResponse
    {
        $manage->releaseHold($payroll);

        return back()->with('success', 'Hold lifted.');
    }

    public function destroy(PayrollPeriod $period, ManagePayrollPeriod $manage): RedirectResponse
    {
        $manage->delete($period);

        return to_route('super-admin.payroll')->with('success', 'Payroll period deleted.');
    }

    /**
     * Suggested holds (a suggestion only; the Super Admin decides): contractors with lost company
     * equipment whose deduction has not been paid through a released payroll yet.
     *
     * @return array<int, string> reason by contractor id
     */
    private function holdSuggestions(PayrollPeriod $period): array
    {
        return DeviceAssignment::query()
            ->where('status', DeviceAssignment::STATUS_LOST)
            ->whereIn('employee_id', $period->payrolls()->select('employee_id'))
            ->whereDoesntHave('deduction.payroll', fn ($query) => $query->where('status', PayrollStatus::Released))
            ->with('device:id,device_name,serial_number')
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($assignments) => 'Lost company equipment: '.$assignments->map(fn (DeviceAssignment $assignment) => $assignment->device->device_name)->implode(', '))
            ->all();
    }

    /**
     * Days between the previous period of the same frequency and this one are not paid by any payroll.
     */
    private function gapBefore(PayrollPeriod $period): ?string
    {
        $previous = PayrollPeriod::query()
            ->where('pay_frequency', $period->pay_frequency)
            ->whereKeyNot($period->id)
            ->whereDate('end_date', '<', $period->start_date)
            ->orderByDesc('end_date')
            ->first();

        if ($previous === null || $previous->end_date->addDay()->greaterThanOrEqualTo($period->start_date)) {
            return null;
        }

        $from = $previous->end_date->addDay();
        $to = $period->start_date->subDay();

        return "Period created. Heads-up: no payroll period covers {$from->format('M j')} – {$to->format('M j, Y')}, so time tracked on those days would not be paid. Create a period for them too.";
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function frequencies(): array
    {
        return PayFrequency::options();
    }
}
