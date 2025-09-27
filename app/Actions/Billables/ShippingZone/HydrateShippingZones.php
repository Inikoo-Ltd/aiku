<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 16:15:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\ShippingZone;

use App\Actions\Billables\ShippingZone\Hydrators\ShippingZoneHydrateUsageInInvoices;
use App\Actions\Billables\ShippingZone\Hydrators\ShippingZoneHydrateUsageInOrders;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Billables\ShippingZone;

class HydrateShippingZones
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:shipping_zones {organisations?*} {--s|slug=}';

    public function __construct()
    {
        $this->model = ShippingZone::class;
    }

    public function handle(ShippingZone $shippingZone): void
    {
        ShippingZoneHydrateUsageInOrders::run($shippingZone->id);
        ShippingZoneHydrateUsageInInvoices::run($shippingZone->id);
    }

}
