<?php

namespace App\Http\Resources\HumanResources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClockingMachineCoordinatePolicyRuleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'day_of_week'   => $this->day_of_week,
            'mode_override' => $this->mode_override?->value ?? $this->mode_override,
            'is_active'     => (bool) $this->is_active,
            'start_at'      => $this->start_at,
            'end_at'        => $this->end_at,
            'start_time'    => $this->start_time,
            'end_time'      => $this->end_time,
        ];
    }
}
