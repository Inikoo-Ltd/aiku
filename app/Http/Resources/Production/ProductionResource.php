<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 15:57:05 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Production;

use App\Models\Production\Production;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Production $production */
        $production = $this;

        return [
            'id' => $production->id,
            'slug' => $production->slug,

        ];
    }
}
