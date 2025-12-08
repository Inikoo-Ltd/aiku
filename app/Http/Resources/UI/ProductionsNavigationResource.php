<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Jun 2023 15:24:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\UI;

use App\Models\Production\Production;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionsNavigationResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Production $production */
        $production = $this;

        return [
            'id' => $production->id,
            'slug' => $production->slug,
            'code' => $production->code,
            'label' => $production->name,
        ];
    }
}
