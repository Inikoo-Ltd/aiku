<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 07 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateProductsNotOnline implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function handle(Shop $shop): void
    {
        $stats = [
            'number_products_not_online' => $shop->products()
                ->where('is_main', true)
                ->whereNull('exclusive_for_customer_id')
                ->where('is_for_sale', true)
                ->whereIn('state', [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING, ProductStateEnum::IN_PROCESS])
                ->where('has_live_webpage', false)
                ->count(),
        ];

        $shop->stats()->update($stats);
    }
}
