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

class EmployeeHanResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var Employee $employee */
        $employee = $this;

        return [
            'id' => $employee->id,
            'organisation_id' => $employee->organisation_id,
            'alias' => $employee->alias,
            'contact_name' => $employee->contact_name,
            'worker_number' => $employee->worker_number,
            'state' => $employee->state,
            'pin' => $employee->pin,
        ];
    }
}
