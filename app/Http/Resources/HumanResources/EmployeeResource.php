<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 21 Oct 2021 12:37:51 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2021, Inikoo
 *  Version 4.0
 */

namespace App\Http\Resources\HumanResources;

use App\Models\HumanResources\Employee;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class EmployeeResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var Employee $employee */
        $employee = $this;

        return [
            'id' => $employee->id,
            'organisation_id' => $employee->organisation_id,
            'organisation_slug' => $employee->organisation->slug,
            'slug' => $employee->slug,
            'contact_name' => $employee->contact_name,
            'worker_number' => $employee->worker_number,
            'state' => $employee->state,
            'employment_start_at' => $employee->employment_start_at,
            'employment_end_at' => $employee->employment_end_at,
            'salary' => $employee->salary,
            'user' => $employee->getUser()?->only('username', 'status'),
            'job_positions' => JobPositionLightResource::collection($employee->jobPositions),
            'emergency_contact' => $employee->emergency_contact,

            'created_at' => $employee->created_at,
            'updated_at' => $employee->updated_at,
        ];
    }
}
