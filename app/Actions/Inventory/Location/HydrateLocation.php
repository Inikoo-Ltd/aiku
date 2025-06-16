<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 18:36:42 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location;

use App\Actions\Inventory\Location\Hydrators\LocationHydratePallets;
use App\Actions\Inventory\Location\Hydrators\LocationHydrateStocks;
use App\Actions\Inventory\Location\Hydrators\LocationHydrateStockValue;
use App\Actions\Inventory\Location\Hydrators\LocationHydrateTotalWeight;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\Location;

class HydrateLocation
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:locations {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = Location::class;
    }

    public function handle(Location $location): void
    {
        LocationHydrateStocks::run($location);
        LocationHydrateStockValue::run($location);
        LocationHydratePallets::run($location);
        LocationHydrateTotalWeight::run($location);
    }


}
