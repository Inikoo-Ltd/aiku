<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 May 2026 09:01:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop\Hydrators;

use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterShop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterShopHydrateMasterFamiliesWithVolGrDiscount implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(MasterShop $masterShop): string
    {
        return $masterShop->id;
    }

    public function handle(MasterShop $masterShop): void
    {
        $count = DB::table('master_product_categories')
            ->where('master_shop_id', $masterShop->id)
            ->where('type', MasterProductCategoryTypeEnum::FAMILY)
            ->where('status', true)
            ->where('has_gr_vol_discount', true)
            ->count();

        $masterShop->stats()->update([
            'number_master_families_with_vol_gr_discount' => $count,
        ]);
    }
}
