<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employeeCode' => $this->employee_code,
            'name' => $this->name,
            'email' => $this->email,
            'phoneNumber' => $this->phone_number,
            'birthday' => $this->birthday?->toDateString(),
            'age' => $this->birthday?->age,
            'address' => $this->address,
            'emergencyContactName' => $this->emergency_contact_name,
            'emergencyContactNumber' => $this->emergency_contact_number,
            'profileCompleted' => $this->profile_completed_at !== null,
            'invited' => $this->isInvited(),
            'invitationExpired' => $this->isInvited() && $this->invitationExpired(),
            'invitationSentAt' => $this->invitation_sent_at?->toIso8601String(),
            'role' => $this->role->value,
            'employmentType' => $this->employment_type?->value,
            'employmentTypeLabel' => $this->employment_type?->label(),
            'status' => $this->status,
            'mustChangePassword' => $this->must_change_password,
            'createdAt' => $this->created_at?->toDateString(),
            'assignments' => $this->whenLoaded(
                'currentRates',
                fn () => RateResource::collection($this->currentRates)->resolve($request),
            ),
            // With no current client, the most recently ended assignments, so the list still shows who they worked for.
            'endedAssignments' => $this->whenLoaded('rates', function () use ($request) {
                if ($this->relationLoaded('currentRates') && $this->currentRates->isNotEmpty()) {
                    return [];
                }

                $lastEnded = $this->rates->whereNotNull('end_date')->max('end_date');

                return $lastEnded === null ? [] : RateResource::collection(
                    $this->rates->filter(fn ($rate) => $rate->end_date?->equalTo($lastEnded))->values()
                )->resolve($request);
            }),
        ];
    }
}
