<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sun, 19 Dec 2021 17:47:04 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2021, Inikoo
 *  Version 4.0
 */

namespace App\Http\Resources\HumanResources;

use App\Models\HumanResources\JobPosition;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class JobPositionResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var JobPosition $jobPosition */
        $jobPosition = $this;

        return [
            'id' => $jobPosition->id,
            'slug' => $jobPosition->slug,
            'name' => $jobPosition->name,
            'number_employees' => $jobPosition->stats->number_employees,
            'scope' => $jobPosition->scope,

        ];
    }
}
