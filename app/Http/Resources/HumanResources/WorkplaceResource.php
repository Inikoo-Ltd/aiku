<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 21 Oct 2021 12:37:51 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2021, Inikoo
 *  Version 4.0
 */

namespace App\Http\Resources\HumanResources;

use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\Helpers\TimezoneResource;
use App\Models\HumanResources\Workplace;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class WorkplaceResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var Workplace $workplace */
        $workplace = $this;

        return [
            'id' => $workplace->id,
            'slug' => $workplace->slug,
            'name' => $workplace->name,
            'type' => $workplace->type,
            'created_at' => $workplace->created_at,
            'updated_at' => $workplace->updated_at,
            'status' => $workplace->status,
            'location' => $workplace->location,
            'timezone' => TimezoneResource::make($workplace->timezone)->getArray(),
            'address' => AddressResource::make($workplace->address)->getArray(),
            'number_clocking_machines' => $workplace->stats->number_clocking_machines,
            'number_clocking_machines_type_static_nfc' => $workplace->stats->number_clocking_machines_type_static_nfc,
            'number_clocking_machines_type_mobile_app' => $workplace->stats->number_clocking_machines_type_mobile_app,
            'number_clockings' => $workplace->stats->number_clockings,

        ];
    }
}
