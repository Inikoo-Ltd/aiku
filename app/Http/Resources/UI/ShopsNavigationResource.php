<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Mar 2023 19:55:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\UI;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopsNavigationResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Shop $shop */
        $shop = $this;

        return [
            'id'     => $shop->id,
            'slug'   => $shop->slug,
            'code'   => $shop->code,
            'label'  => $shop->name,
            'state'  => $shop->state,
            'type'   => $shop->type,
            'route'  => [
                'name'       => 'grp.org.shops.show.dashboard.show',
                'parameters' => [
                    $shop->organisation->slug,
                    $shop->slug
                ]
            ],
            'is_external'    => $shop->type == ShopTypeEnum::EXTERNAL,
            'external_api_problem'   => !$shop->external_shop_platform_status && !is_null($shop->external_shop_connection_failed_at) // true if connection fails & fail timestamp exists
        ];
    }
}
