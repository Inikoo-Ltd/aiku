<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Jun 2024 20:15:28 Central European Summer Time, Abu Dhabi Airport
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateSubDepartments implements ShouldBeUnique
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
            'number_sub_departments' => $shop->subDepartments()->count(),
            'number_current_sub_departments' => $shop->subDepartments()->whereIn(
                'state',
                [
                    ProductCategoryStateEnum::ACTIVE,
                    ProductCategoryStateEnum::DISCONTINUING
                ]
            )->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'sub_departments',
                field: 'state',
                enum: ProductCategoryStateEnum::class,
                models: ProductCategory::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id)->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT);
                }
            )
        );

        $shop->stats()->update($stats);
    }


}
