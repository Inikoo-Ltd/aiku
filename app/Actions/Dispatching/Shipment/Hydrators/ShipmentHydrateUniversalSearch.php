<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 10:02:57 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Dispatching\Shipment\Hydrators;

use App\Models\Dispatching\Shipment;
use Lorisleiva\Actions\Concerns\AsAction;

class ShipmentHydrateUniversalSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(Shipment $shipment): void
    {
        $shipment->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $shipment->group_id,
                'organisation_id'   => $shipment->organisation_id,
                'organisation_slug' => $shipment->organisation->slug,
                'shop_id'           => $shipment?->shop_id,
                'shop_slug'         => $shipment?->shop?->slug,
                'customer_id'       => $shipment?->customer_id,
                'customer_slug'     => $shipment?->customer?->slug,
                'sections'          => ['dispatching'],
                'haystack_tier_1'   => $shipment->tracking ?? '',
                'haystack_tier_2'   => ''
            ]
        );
    }

}
