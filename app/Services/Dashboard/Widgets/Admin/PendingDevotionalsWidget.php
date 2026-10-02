<?php

namespace App\Services\Dashboard\Widgets\Admin;

use App\Models\User;
use App\Services\Dashboard\Contracts\DashboardWidget;
use Carbon\CarbonImmutable;

/**
 * Active contractors who have not uploaded today's devotional yet. Compliance only (Blueprint §9).
 */
class PendingDevotionalsWidget implements DashboardWidget
{
    private const LIMIT = 5;

    public function __construct(private readonly CarbonImmutable $today) {}

    public function key(): string
    {
        return 'devotionals';
    }

    /**
     * @return array{expected: int, submitted: int, pending: int, names: list<string>}
     */
    public function data(): array
    {
        $employees = User::query()->workforce()->active();

        $pending = (clone $employees)
            ->whereDoesntHave('devotionals', fn ($query) => $query->whereDate('date', $this->today));

        $expected = (clone $employees)->count();
        $pendingCount = (clone $pending)->count();

        return [
            'expected' => $expected,
            'submitted' => $expected - $pendingCount,
            'pending' => $pendingCount,
            'names' => $pending->orderBy('name')->limit(self::LIMIT)->pluck('name')->all(),
        ];
    }
}
