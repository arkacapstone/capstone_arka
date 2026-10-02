<?php

namespace App\Actions\Attendance;

use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Notifications\VerificationReminder;
use App\Notifications\VerifiedPeriodSubmitted;
use App\Services\ActivityLogger;
use App\Services\Notifier;
use App\Services\Payroll\PayrollCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The Admin's side of payroll verification. Contractors fix their attendance once and submit it;
 * the Admin reviews their changes, reminds whoever has not submitted, and is the last to submit:
 * the verified period goes to the Super Admin, who then only processes payroll.
 */
class ReviewPeriodVerification
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly Notifier $notifier,
    ) {}

    /**
     * Contractors paid in the period who have not submitted their attendance as verified yet.
     *
     * @return Collection<int, User>
     */
    public static function notSubmitted(PayrollPeriod $period): Collection
    {
        $submitted = $period->verifications()->whereNotNull('verified_at')->pluck('employee_id');

        return PayrollCalculator::employeesFor($period)->whereNotIn('id', $submitted)->values();
    }

    /**
     * Why the Admin cannot submit the period yet, if anything stops them.
     */
    public static function submitBlocker(PayrollPeriod $period): ?string
    {
        if ($period->status !== PayrollPeriodStatus::Verification) {
            return 'This period is not in verification.';
        }

        if ($period->isSubmittedByAdmin()) {
            return 'This period was already submitted to the Super Admin.';
        }

        $waiting = self::notSubmitted($period)->count();

        // The Admin submits last: after everyone has submitted, or once fixing has closed for the rest.
        if ($waiting > 0 && $period->fixWindowOpen()) {
            return ($waiting === 1 ? '1 contractor has' : "{$waiting} contractors have")
                .' not submitted yet. Send a reminder, or submit after fixing closes at '.$period->fixDeadline()->format('g:i A, M j').'.';
        }

        return null;
    }

    /**
     * Notifies every contractor who has not submitted that it is cut-off, with the time left to fix.
     */
    public function remind(User $admin, PayrollPeriod $period): int
    {
        $this->ensureOpen($period);

        // Everyone still waiting, the Admin included if they are paid in this period too, so the count matches the button.
        $recipients = self::notSubmitted($period);

        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages(['period' => 'Every contractor has already submitted their attendance.']);
        }

        Notification::send($recipients, new VerificationReminder($period));
        $period->update(['last_reminded_at' => now(), 'last_reminded_count' => $recipients->count()]);
        $this->activity->log('attendance', 'Sent verification reminder', $period, "{$period->period_name} · {$recipients->count()} contractor(s) · by {$admin->name}");

        return $recipients->count();
    }

    public function submit(User $admin, PayrollPeriod $period): PayrollPeriod
    {
        if ($blocker = self::submitBlocker($period)) {
            throw ValidationException::withMessages(['period' => $blocker]);
        }

        $period->update(['admin_submitted_at' => now(), 'admin_submitted_by' => $admin->id]);

        $this->activity->log('attendance', 'Submitted verified period to the Super Admin', $period, $period->period_name);
        $this->notifier->superAdmins(new VerifiedPeriodSubmitted($period, $admin));

        return $period;
    }

    private function ensureOpen(PayrollPeriod $period): void
    {
        if ($period->status !== PayrollPeriodStatus::Verification || $period->isSubmittedByAdmin()) {
            throw ValidationException::withMessages(['period' => 'This period is no longer open for verification.']);
        }
    }
}
