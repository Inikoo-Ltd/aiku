<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPartnerBuyingPriceFactor
{
    use AsObject;

    public const HUB_PARTNER_DISCOUNT = 0.45;

    public function handle(OrgPartner $orgPartner): float
    {
        if (GetPartnerLandedCost::appliesTo($orgPartner)) {
            return 1.0;
        }

        return round(1 - self::hubPartnerDiscount($orgPartner->partner), 4);
    }

    /**
     * A fixed share off the hub's list price, kept in the hub's settings rather than in shop offers
     * so a changed or missing offer can not reprice the partners.
     */
    public static function hubPartnerDiscount(Organisation $hub): float
    {
        return max(0.0, min(1.0, (float) Arr::get($hub->settings, 'procurement.partner_discount', self::HUB_PARTNER_DISCOUNT)));
    }
}
