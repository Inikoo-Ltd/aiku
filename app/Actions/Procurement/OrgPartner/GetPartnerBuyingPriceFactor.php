<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Models\Procurement\OrgPartner;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPartnerBuyingPriceFactor
{
    use AsObject;

    public function handle(OrgPartner $orgPartner): float
    {
        $shopId = Arr::get($orgPartner->partner->settings, 'procurement.shop_id');
        if (!$shopId) {
            return 1.0;
        }

        $customer = GetPartnerIntercompanyCustomer::run($orgPartner, $shopId);

        return $customer ? GetPartnerCustomerDiscount::run($customer) : 1.0;
    }
}
