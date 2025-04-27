<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Apr 2025 16:19:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterFamilyHydrateFamilies implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(MasterProductCategory $masterFamily): string
    {
        return $masterFamily->id;
    }

    public function handle(MasterProductCategory $masterFamily): void
    {

        if ($masterFamily->type != MasterProductCategoryTypeEnum::FAMILY) {
            return;
        }


        $stats = [
            'number_families' => DB::table('product_categories')->where('type', ProductCategoryTypeEnum::FAMILY)->where('master_product_category_id', $masterFamily->id)->count(),
            'number_current_families' => DB::table('product_categories')->where('type', ProductCategoryTypeEnum::FAMILY)->where('master_product_category_id', $masterFamily->id)->whereIn('state', [
                ShopStateEnum::OPEN,
                ShopStateEnum::CLOSING_DOWN,
            ])->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'families',
                field: 'state',
                enum: ProductCategoryStateEnum::class,
                models: ProductCategory::class,
                where: function ($q) use ($masterFamily) {
                    $q->where('type', ProductCategoryTypeEnum::FAMILY)->where('master_product_category_id', $masterFamily->id);
                }
            )
        );

        $masterFamily->stats()->update($stats);
    }


}
