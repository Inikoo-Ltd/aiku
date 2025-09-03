<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Apr 2025 15:18:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterShop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterShopHydrateMasterFamiliesWithPendingMasterAssets implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(MasterShop $masterShop): string
    {
        return $masterShop->id;
    }

    public function handle(MasterShop $masterShop): void
    {
        $count = DB::table('master_product_categories')
            ->join('master_product_category_stats', 'master_product_categories.id', '=', 'master_product_category_stats.master_product_category_id')
            ->where('master_product_categories.master_shop_id', $masterShop->id)
            ->where('master_product_categories.type', MasterProductCategoryTypeEnum::FAMILY)
            ->where('master_product_category_stats.number_pending_master_assets', '>', 0)
            ->count();

        $stats = [
            'number_master_families_with_pending_master_assets' => $count,
        ];

        $masterShop->stats()->update($stats);
    }


}
