<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 12:05:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\ShippingZoneSchema\Hydrators;

use App\Models\Billables\ShippingZoneSchema;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShippingZoneSchemaHydrateShippingZones implements ShouldBeUnique
{
    use AsAction;


    public function getJobUniqueId(ShippingZoneSchema $shippingZoneSchema): string
    {
        return $shippingZoneSchema->id;
    }

    public function handle(ShippingZoneSchema $shippingZoneSchema): void
    {
        $stats = [
            'number_shipping_zones' => $shippingZoneSchema->shippingZones->count(),
        ];

        $shippingZoneSchema->stats()->update($stats);
    }

}
