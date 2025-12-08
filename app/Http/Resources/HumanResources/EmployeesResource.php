<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sat, 22 Jan 2022 03:06:40 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

namespace App\Http\Resources\HumanResources;

use App\Models\HumanResources\Employee;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

/**
 * @property mixed $job_positions
 */
class EmployeesResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var Employee $employee */
        $employee = $this;

        return [
            'id' => $employee->id,
            'slug' => $employee->slug,
            'worker_number' => $employee->worker_number,
            'name' => $employee->contact_name,
            'contact_name' => $employee->contact_name,
            'job_title' => $employee->job_title,
            'state' => $employee->state,
            'positions' => $this->job_positions ? JobPositionLightResource::collection(json_decode($this->job_positions)) : null,
            'state_icon' => $employee->state->stateIcon()[$employee->state->value],
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
        ];
    }
}
