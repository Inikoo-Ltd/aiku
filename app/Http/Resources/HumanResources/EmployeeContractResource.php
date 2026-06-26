<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\HumanResources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property float $annual_leave_days
 * @property string|null $notes
 * @property-read \App\Models\HumanResources\EmployeeLeaveBalance|null $leaveBalance
 */
class EmployeeContractResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        $routeParameters = [
            ...$request->route()->originalParameters(),
            'contract' => $this->id,
        ];

        $balance = $this->leaveBalance;

        return [
            'id'                => $this->id,
            'start_date'        => $this->start_date->toDateString(),
            'end_date'          => $this->end_date?->toDateString(),
            'annual_leave_days' => $this->annual_leave_days,
            'notes'             => $this->notes,
            'balance'           => $balance ? [
                'id'            => $balance->id,
                'annual_used'   => $balance->annual_used,
                'medical_used'  => $balance->medical_used,
                'unpaid_used'   => $balance->unpaid_used,
                'annual_remaining' => $balance->annual_remaining,
            ] : null,
            'edit_route'             => [
                'name'       => 'grp.org.hr.employees.show.contracts.edit',
                'parameters' => $routeParameters,
            ],
            'delete_route'           => [
                'name'       => 'grp.models.employee.contracts.delete',
                'parameters' => ['contract' => $this->id],
            ],
            'generate_balance_route' => $balance ? null : [
                'name'       => 'grp.models.employee.contracts.generate_balance',
                'parameters' => ['contract' => $this->id],
            ],
        ];
    }
}
