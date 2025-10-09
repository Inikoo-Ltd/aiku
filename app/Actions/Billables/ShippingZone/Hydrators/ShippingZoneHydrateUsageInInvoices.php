<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 16:57:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\ShippingZone\Hydrators;

use App\Models\Billables\shippingZone;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShippingZoneHydrateUsageInInvoices implements ShouldBeUnique
{
    use AsAction;


    public function getJobUniqueId(int $shippingZoneID): string
    {
        return $shippingZoneID;
    }

    public function handle(int $shippingZoneID): void
    {
        $shippingZone = ShippingZone::find($shippingZoneID);
        if (!$shippingZone) {
            return;
        }

        $amount = $shippingZone->invoices()
            ->where('invoices.in_process', false)
            ->sum('shipping_amount');

        $orgAmount = $shippingZone->invoices()
            ->where('invoices.in_process', false)
            ->selectRaw('COALESCE(SUM(shipping_amount * org_exchange), 0) as total')
            ->value('total');

        $grpAmount = $shippingZone->invoices()
            ->where('invoices.in_process', false)
            ->selectRaw('COALESCE(SUM(shipping_amount * grp_exchange), 0) as total')
            ->value('total');



        $stats = [
            'amount'     => $amount ?? 0,
            'org_amount' => $orgAmount ?? 0,
            'grp_amount' => $grpAmount ?? 0,
        ];


        $shippingZone->stats()->update($stats);
    }

}
