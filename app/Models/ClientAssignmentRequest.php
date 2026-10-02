<?php

namespace App\Models;

use App\Enums\EmploymentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An Admin's request to give a contractor a client. The Super Admin approves it, setting the rate,
 * or rejects it. Once approved, the Admin can schedule the contractor for that client.
 */
#[Fillable([
    'employee_id', 'client_id', 'client_name', 'employment_type', 'requested_by', 'start_date',
    'status', 'reviewed_by', 'reviewed_at', 'review_note', 'rate_id',
])]
class ClientAssignmentRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'start_date' => 'immutable_date',
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
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * The existing client's name, or the new one the Admin typed.
     */
    public function clientName(): string
    {
        return $this->client?->client_name ?? (string) $this->client_name;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Waiting for approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => 'Cancelled',
        };
    }
}
