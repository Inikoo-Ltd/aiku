<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 07 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateProductsWithNoImage implements ShouldBeUnique
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
            'number_products_no_images' => $shop->products()
                ->whereNull('exclusive_for_customer_id')
                ->whereNull('image_id')
                ->count(),
        ];

        $shop->stats()->update($stats);
    }
}
