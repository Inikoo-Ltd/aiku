<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:37:16 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Http\Resources\HasSelfCall;
use App\Models\Helpers\Timezone;
use Illuminate\Http\Resources\Json\JsonResource;

class TimezoneResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        if ($this->resource === null) {
            return [
                'id' => null,
                'name' => null,
                'offset' => null,
            ];
        }

        /** @var Timezone $timezone */
        $timezone = $this;

        return [
            'id' => $timezone->id,
            'name' => $timezone->name,
            'offset' => $timezone->offset,
        ];
    }
}
