<?php

namespace App\Http\Resources\HumanResources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

/**
 * @property int $id
 * @property int $year
 * @property int $annual_days
 * @property int $annual_used
 * @property int $medical_days
 * @property int $medical_used
 * @property int $unpaid_days
 * @property int $unpaid_used
 * @property int $annual_remaining
 * @property int $medical_remaining
 * @property int $unpaid_remaining
 */
class LeaveBalanceResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        return [
            'id'               => $this->id,
            'year'             => $this->year,
            'annual_days'      => $this->annual_days,
            'annual_used'      => $this->annual_used,
            'annual_remaining' => $this->annual_remaining,
            'medical_days'     => $this->medical_days,
            'medical_used'     => $this->medical_used,
            'medical_remaining' => $this->medical_remaining,
            'unpaid_days'      => $this->unpaid_days,
            'unpaid_used'      => $this->unpaid_used,
            'unpaid_remaining' => $this->unpaid_remaining,
        ];
    }
}
