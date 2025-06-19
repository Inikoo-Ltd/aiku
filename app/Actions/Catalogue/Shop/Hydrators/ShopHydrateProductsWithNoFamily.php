<?php

/*
 * author Arya Permana - Kirin
 * created on 19-06-2025-15h-23m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateProductsWithNoFamily implements ShouldBeUnique
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
            'number_products_no_family' => $shop->products()->where('is_main', true)->whereNull('exclusive_for_customer_id')->whereNull('family_id')->count()
        ];

        $shop->stats()->update($stats);
    }

}
