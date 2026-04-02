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
        ];
    }
}
