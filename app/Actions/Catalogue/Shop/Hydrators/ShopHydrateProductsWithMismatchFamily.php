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

class ShopHydrateProductsWithMismatchFamily implements ShouldBeUnique
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
            'number_products_mismatch_family' => $shop->products()
                ->where('products.is_main', true)
                ->whereNull('products.exclusive_for_customer_id')
                ->join('master_assets', 'master_assets.id', 'products.master_product_id')
                ->join('product_categories as family', 'family.id', 'products.family_id')
                ->whereColumn('family.master_product_category_id', '!=', 'master_assets.master_family_id')
                ->count(),
        ];

        $shop->stats()->update($stats);
    }
}
