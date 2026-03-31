<?php

namespace App\Http\Resources\HumanResources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClockingMachineCoordinatePolicyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'organisation_id'   => $this->organisation_id,
            'scope_type'        => $this->scope_type,
            'scope_id'          => $this->scope_id,
            'clocking_machine_id' => $this->clocking_machine_id,
            'mode'              => $this->mode?->value ?? $this->mode,
            'is_active'         => (bool) $this->is_active,
            'start_at'          => $this->start_at,
            'end_at'            => $this->end_at,
            'reason'            => $this->reason,
            'rules_count'       => (int) ($this->rules_count ?? 0),
            'rules'             => ClockingMachineCoordinatePolicyRuleResource::collection($this->whenLoaded('rules')),
        ];
    }
}
