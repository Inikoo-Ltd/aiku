<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Mar 2026 00:42:35 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop\Hydrators;

use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterShopHydrateNumberMismatches implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(MasterShop $masterShop): string
    {
        return $masterShop->id;
    }

    public function handle(MasterShop $masterShop): void
    {
        $baseQueryMasterProduct = MasterAsset::where('master_shop_id', $masterShop->id)
            ->where('mismatch_detected', true);

        $masterShop->stats()->update(['number_mismatched_master_products' => $baseQueryMasterProduct->clone()->count()]);
        $masterShop->stats()->update(['number_mismatched_master_products_active' => $baseQueryMasterProduct->clone()->where('status', true)->count()]);
        $masterShop->stats()->update(['number_mismatched_master_products_inactive' => $baseQueryMasterProduct->clone()->where('status', false)->count()]);


        $baseQueryMasterFamily = MasterProductCategory::where('master_shop_id', $masterShop->id)
            ->where('type', MasterProductCategoryTypeEnum::FAMILY)
            ->where('mismatch_detected', true);

        $masterShop->stats()->update(['number_mismatched_master_families' => $baseQueryMasterFamily->clone()->count()]);
        $masterShop->stats()->update(['number_mismatched_master_families_active' => $baseQueryMasterFamily->clone()->where('status', true)->count()]);
        $masterShop->stats()->update(['number_mismatched_master_families_inactive' => $baseQueryMasterFamily->clone()->where('status', false)->count()]);
    }


}
