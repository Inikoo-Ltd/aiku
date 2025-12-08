<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sat, 22 Jan 2022 03:06:40 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

namespace App\Http\Resources\HumanResources;

use App\Models\HumanResources\Workplace;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class WorkplaceInertiaResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var Workplace $workplace */
        $workplace = $this;

        return [
            'id' => $workplace->id,
            'slug' => $workplace->slug,
            'name' => $workplace->name,
            'type' => $workplace->type,
            'organisation_name' => $workplace->organisation_name,
            'organisation_slug' => $workplace->organisation_slug,
        ];
    }
}
