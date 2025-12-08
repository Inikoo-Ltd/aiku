<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sun, 19 Dec 2021 17:51:24 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2021, Inikoo
 *  Version 4.0
 */

namespace App\Http\Resources\HumanResources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class JobPositionLightResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var \App\Models\HumanResources\JobPosition $jobPosition */
        $jobPosition = $this;

        return [
            'slug' => $jobPosition->slug,
            'name' => $jobPosition->name,
        ];
    }
}
