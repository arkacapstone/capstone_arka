<?php

namespace App\Http\Resources;

use App\Models\Rate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Rate
 */
class RateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'name' => $this->client->client_name,
                'code' => $this->client->client_code,
            ]),
            'grossPay' => (float) $this->gross_pay,
            'payFrequency' => $this->pay_frequency->value,
            'payFrequencyLabel' => $this->pay_frequency->label(),
            'workingDays' => $this->working_days,
            'hoursPerDay' => $this->hours_per_day,
            'hourlyRate' => round($this->hourlyRate(), 2),
            'dailyRate' => round($this->dailyRate(), 2),
            'effectiveDate' => $this->effective_date->toDateString(),
            'endDate' => $this->end_date?->toDateString(),
        ];
    }
}
