<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 12:07:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\ShippingZoneSchema;

use App\Actions\Billables\ShippingZoneSchema\Hydrators\ShippingZoneSchemaHydrateShippingZones;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Billables\ShippingZoneSchema;

class HydrateShippingZoneSchemas
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:shipping_zone_schemas {organisations?*} {--s|slug=}';

    public function __construct()
    {
        $this->model = ShippingZoneSchema::class;
    }

    public function handle(ShippingZoneSchema $shippingZoneSchema): void
    {
        ShippingZoneSchemaHydrateShippingZones::run($shippingZoneSchema);
    }

}
