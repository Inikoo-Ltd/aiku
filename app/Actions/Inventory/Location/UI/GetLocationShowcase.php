<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 25 May 2023 15:30:45 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Inventory\Location\UI;

use App\Models\Inventory\Location;
use Lorisleiva\Actions\Concerns\AsObject;

class GetLocationShowcase
{
    use AsObject;

    public function handle(Location $location): array
    {
        return [
            'stats' => [
                'max_volume'                => $location->max_volume,
                'max_weight'                => $location->max_weight,
                'number_org_stock_slots'    => $location->stats->number_org_stock_slots,
                'empty_stock_slots'         => $location->stats->number_empty_stock_slots,
            ],
            'updateRoute' => [
                'name'       => 'grp.models.location.update',
                'parameters' => [$location->id]
            ],
            'radioTabs' => [
                'allow_stocks'       => $location->allow_stocks,
                'allow_fulfilment'   => $location->allow_fulfilment,
                'allow_dropshipping' => $location->allow_dropshipping,
                'is_goods_out'       => $location->is_goods_out,
            ],
            'location' => [
                'id'   => $location->id,
                'slug' => $location->slug,
                'code' => $location->code
            ]
        ];
    }
}
