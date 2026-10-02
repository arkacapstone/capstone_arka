<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An overtime ticket (Payroll Formula Reference: "Overtime is an exception, not a formula"). The
 * contractor files it with the client handler who approved the overtime; the Super Admin approves
 * it with the amount, and it is paid in the contractor's next payroll for that client.
 */
#[Fillable([
    'employee_id', 'client_id', 'date', 'minutes', 'client_handler', 'reason',
    'status', 'amount', 'reviewed_by', 'reviewed_at', 'review_note', 'payroll_id',
])]
class OvertimeRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'minutes' => 'integer',
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<Payroll, $this>
     */
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', self::STATUS_APPROVED);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * "2:30" for 150 minutes.
     */
    public function duration(): string
    {
        return intdiv($this->minutes, 60).':'.str_pad((string) ($this->minutes % 60), 2, '0', STR_PAD_LEFT);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Waiting for approval',
            self::STATUS_APPROVED => $this->payroll_id ? 'Approved · in payroll' : 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => 'Withdrawn',
        };
    }
}
