<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:37:16 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $iso3
 * @property string $name
 */
class CountryResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [

            'code' => $this->code,
            'iso3' => $this->iso3,
            'name' => $this->name,
        ];
    }
}
