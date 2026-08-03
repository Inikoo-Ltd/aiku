<?php

namespace App\Http\Resources\HumanResources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

/**
 * @property int $id
 * @property int $employee_id
 * @property float $annual_used
 * @property float $unpaid_used
 * @property float $medical_used
 * @property float $annual_remaining
 */
class LeaveBalanceResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        return [
            'id'               => $this->id,
            'annual_days'      => $this->contract?->annual_leave_days ?? 0,
            'annual_used'      => $this->annual_used,
            'annual_remaining' => $this->annual_remaining,
            'medical_used'     => $this->medical_used,
            'unpaid_used'      => $this->unpaid_used,
        ];
    }
}
