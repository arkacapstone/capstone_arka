<?php

namespace App\Http\Resources;

use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Schedule
 */
class ScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ended = $this->end_date !== null && $this->end_date->lessThan(CarbonImmutable::today());

        return [
            'id' => $this->id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'name' => $this->employee->name,
                'code' => $this->employee->employee_code,
            ]),
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'name' => $this->client->client_name,
            ]),
            'clientId' => $this->client_id,
            'employeeId' => $this->employee_id,
            'workingDays' => $this->working_days ?? [],
            'startTime' => substr($this->start_time, 0, 5),
            'endTime' => substr($this->end_time, 0, 5),
            'crossesMidnight' => $this->crossesMidnight(),
            'expectedHours' => $this->expectedHours(),
            'breakAllowance' => $this->break_allowance_minutes,
            'startDate' => $this->start_date->toDateString(),
            'endDate' => $this->end_date?->toDateString(),
            // Ended = replaced or finished; kept as history, never deleted.
            'status' => $ended ? 'ended' : $this->status,
        ];
    }
}
