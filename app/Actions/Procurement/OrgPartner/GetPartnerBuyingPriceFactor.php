<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Models\Procurement\OrgPartner;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPartnerBuyingPriceFactor
{
    use AsObject;

    public function handle(OrgPartner $orgPartner): float
    {
        foreach (GetPartnerSellingShopIds::run($orgPartner->partner) as $shopId) {
            $customer = GetPartnerIntercompanyCustomer::run($orgPartner, $shopId);
            if ($customer) {
                return GetPartnerCustomerDiscount::run($customer);
            }
        }

        return 1.0;
    }
}
