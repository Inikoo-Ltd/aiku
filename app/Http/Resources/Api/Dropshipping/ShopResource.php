<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Jun 2024 12:53:40 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Api\Dropshipping;

use App\Models\Catalogue\Shop;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Shop $shop */
        $shop = $this;

        return [
            'id'      => $shop->id,
            'slug'    => $shop->slug,
            'code'    => $shop->code,
            'name'    => $shop->name,
            'state'   => $shop->state,
            'created' => $shop->created_at,
            'updated' => $shop->updated_at,
            'website' => $shop->website ? WebsitesResource::make($shop->website) : null,
        ];
    }
}
