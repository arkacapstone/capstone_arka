<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;

/**
 * The shared filter pattern of every report: Contractor (all or one), From / To, and an optional status.
 */
final readonly class ReportFilters
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?int $employeeId = null,
        public ?string $status = null,
    ) {}

    /**
     * @param  array{from?: ?string, to?: ?string, employee?: ?int, status?: ?string}  $input
     */
    public static function fromInput(array $input): self
    {
        $today = CarbonImmutable::today();

        return new self(
            from: isset($input['from']) ? CarbonImmutable::parse($input['from']) : $today->startOfMonth(),
            to: isset($input['to']) ? CarbonImmutable::parse($input['to']) : $today,
            employeeId: isset($input['employee']) ? (int) $input['employee'] : null,
            status: $input['status'] ?? null,
        );
    }

    /**
     * @return array{from: string, to: string, employee: ?int, status: ?string}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'employee' => $this->employeeId,
            'status' => $this->status,
        ];
    }
}
