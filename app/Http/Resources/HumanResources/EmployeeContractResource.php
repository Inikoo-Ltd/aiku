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
 * @property int $annual_leave_days
 * @property string|null $notes
 */
class EmployeeContractResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        $routeParameters = [
            ...$request->route()->originalParameters(),
            'contract' => $this->id,
        ];

        return [
            'id'                => $this->id,
            'start_date'        => $this->start_date->toDateString(),
            'end_date'          => $this->end_date?->toDateString(),
            'annual_leave_days' => $this->annual_leave_days,
            'notes'             => $this->notes,
            'edit_route'   => [
                'name'       => 'grp.org.hr.employees.show.contracts.edit',
                'parameters' => $routeParameters
            ],
            'delete_route' => [
                'name'       => 'grp.models.employee.contracts.delete',
                'parameters' => ['contract' => $this->id]
            ]
        ];
    }
}
