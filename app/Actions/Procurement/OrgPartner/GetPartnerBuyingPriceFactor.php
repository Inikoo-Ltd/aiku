<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Models\Procurement\OrgPartner;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPartnerBuyingPriceFactor
{
    use AsObject;

    /**
     * Cached for ten minutes: finding the intercompany customer scans the seller's customers by name
     * when the buyer has no account in a shop (about 0.7 s), and pages call this several times.
     */
    public function handle(OrgPartner $orgPartner): float
    {
        if (GetPartnerLandedCost::appliesTo($orgPartner)) {
            return 1.0;
        }

        return Cache::remember("partner-buying-price-factor:$orgPartner->id", now()->addMinutes(10), fn () => $this->factor($orgPartner));
    }

    private function factor(OrgPartner $orgPartner): float
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
