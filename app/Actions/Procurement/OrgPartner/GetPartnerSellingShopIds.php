<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPartnerSellingShopIds
{
    use AsObject;

    /**
     * The shops a seller sells to the other companies from, first choice first
     * (settings procurement.shop_ids, falling back to procurement.shop_id, then to every
     * non-external shop of the seller).
     *
     * @return array<int, int>
     */
    public function handle(Organisation $seller): array
    {
        $shopIds = array_values(array_unique(array_map('intval', array_filter(Arr::wrap(Arr::get($seller->settings, 'procurement.shop_ids'))))));
        if ($shopIds) {
            return $shopIds;
        }

        $shopId = (int) Arr::get($seller->settings, 'procurement.shop_id');
        if ($shopId) {
            return [$shopId];
        }

        return $seller->shops()->where('type', '!=', ShopTypeEnum::EXTERNAL->value)->orderBy('id')->pluck('id')->all();
    }
}
