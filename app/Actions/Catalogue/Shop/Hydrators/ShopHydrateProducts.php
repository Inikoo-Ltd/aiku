<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Mar 2023 16:06:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateProducts implements ShouldBeUnique
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
            'number_products' => $shop->products()->where('is_main', true)->whereNull('exclusive_for_customer_id')->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'products',
                field: 'state',
                enum: ProductStateEnum::class,
                models: Product::class,
                where: function ($q) use ($shop) {
                    $q->where('is_main', true)->where('shop_id', $shop->id);
                }
            )
        );


        $stats['number_current_products'] = Arr::get($stats, 'number_products_state_active', 0) +
            Arr::get($stats, 'number_products_state_discontinuing', 0);


        $shop->stats()->update($stats);
    }

}
