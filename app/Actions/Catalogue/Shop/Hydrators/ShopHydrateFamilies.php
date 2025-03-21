<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 31 Mar 2024 15:32 Malaysia Time, Plane KL - Bali
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateFamilies implements ShouldBeUnique
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
            'number_families' => $shop->getFamilies()->count(),
            'number_current_families' => $shop->getFamilies()->whereIn('state', [
                ProductCategoryStateEnum::ACTIVE,
                ProductCategoryStateEnum::DISCONTINUING,
            ])->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'families',
                field: 'state',
                enum: ProductCategoryStateEnum::class,
                models: ProductCategory::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id)->where('type', ProductCategoryTypeEnum::FAMILY);
                }
            )
        );
        $shop->stats()->update($stats);
    }


}
