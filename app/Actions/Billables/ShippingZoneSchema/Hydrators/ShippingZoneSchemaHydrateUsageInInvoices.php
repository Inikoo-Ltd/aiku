<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 17:05:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\ShippingZoneSchema\Hydrators;

use App\Models\Billables\ShippingZoneSchema;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShippingZoneSchemaHydrateUsageInInvoices implements ShouldBeUnique
{
    use AsAction;


    public function getJobUniqueId(int $shippingZoneSchemaId): string
    {
        return $shippingZoneSchemaId;
    }

    public function handle(int $shippingZoneSchemaId): void
    {
        $shippingZoneSchema = ShippingZoneSchema::find($shippingZoneSchemaId);
        if (!$shippingZoneSchema) {
            return;
        }

        $amount = $shippingZoneSchema->invoices()
            ->where('invoices.in_process', false)
            ->sum('shipping_amount');

        $orgAmount = $shippingZoneSchema->invoices()
            ->where('invoices.in_process', false)
            ->selectRaw('COALESCE(SUM(shipping_amount * org_exchange), 0) as total')
            ->value('total');

        $grpAmount = $shippingZoneSchema->invoices()
            ->where('invoices.in_process', false)
            ->selectRaw('COALESCE(SUM(shipping_amount * grp_exchange), 0) as total')
            ->value('total');


        $stats = [
            'amount'     => $amount ?? 0,
            'org_amount' => $orgAmount ?? 0,
            'grp_amount' => $grpAmount ?? 0,
        ];

        $shippingZoneSchema->stats()->update($stats);
    }

}
