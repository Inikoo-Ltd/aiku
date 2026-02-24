<?php

/*
 * author Louis Perez
 * created on 09-02-2026-15h-29m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydratePendingBackInStockReminders implements ShouldBeUnique
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
            'pending_back_in_stock_products_count' => $shop
                                    ->backInStockReminders()
                                    ->leftJoin('products', 'products.id', 'back_in_stock_reminders.product_id')
                                    ->where('products.is_main', true)
                                    ->whereIn('products.state', [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING])
                                    ->count(),
        ];

        $shop->stats()->update($stats);
    }

}
