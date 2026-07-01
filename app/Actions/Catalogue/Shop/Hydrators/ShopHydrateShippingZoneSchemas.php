<?php

/*
 * Author: stewicca <wiccaalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\ShippingZoneSchema\ShippingZoneSchemaStateEnum;
use App\Models\Billables\ShippingZoneSchema;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateShippingZoneSchemas implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Shop $shop): string
    {
        return (string) $shop->id;
    }

    public function handle(Shop $shop): void
    {
        $stats = [
            'number_shipping_zone_schemas' => $shop->shippingZoneSchemas()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'shipping_zone_schemas',
                field: 'state',
                enum: ShippingZoneSchemaStateEnum::class,
                models: ShippingZoneSchema::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id);
                }
            )
        );

        $shop->stats()->update($stats);
    }
}
