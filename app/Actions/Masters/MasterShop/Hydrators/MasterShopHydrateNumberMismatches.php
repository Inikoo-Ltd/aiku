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

        $countMismatch = MasterAsset::where('master_shop_id', $masterShop->id)
            ->where('mismatch_detected', true)
            ->count();

        $countMismatchActive = MasterAsset::where('master_shop_id', $masterShop->id)
            ->where('mismatch_detected', true)
            ->where('status', true)
            ->count();

        $countMismatchInactive = MasterAsset::where('master_shop_id', $masterShop->id)
            ->where('mismatch_detected', true)
            ->where('status', false)
            ->count();

        $masterShop->stats()->update([
            'number_mismatched_master_products'          => $countMismatch,
            'number_mismatched_master_products_active'   => $countMismatchActive,
            'number_mismatched_master_products_inactive' => $countMismatchInactive,
        ]);


        $countMismatch = MasterProductCategory::where('master_shop_id', $masterShop->id)
            ->where('type', MasterProductCategoryTypeEnum::FAMILY)
            ->where('mismatch_detected', true)
            ->count();

        $countMismatchActive = MasterProductCategory::where('master_shop_id', $masterShop->id)
            ->where('type', MasterProductCategoryTypeEnum::FAMILY)
            ->where('mismatch_detected', true)
            ->where('status', true)
            ->count();

        $countMismatchInactive = MasterProductCategory::where('master_shop_id', $masterShop->id)
            ->where('type', MasterProductCategoryTypeEnum::FAMILY)
            ->where('mismatch_detected', true)
            ->where('status', false)
            ->count();

        $masterShop->stats()->update([
            'number_mismatched_master_families'          => $countMismatch,
            'number_mismatched_master_families_active'   => $countMismatchActive,
            'number_mismatched_master_families_inactive' => $countMismatchInactive,
        ]);


    }


}
