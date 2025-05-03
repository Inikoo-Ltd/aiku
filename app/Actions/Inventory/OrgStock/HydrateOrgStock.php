<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 18:37:32 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateLocations;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateMovements;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateSalesIntervals;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateValueInLocations;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\OrgStock;

class HydrateOrgStock
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:org_stocks {organisations?*} {--s|slugs=} ';

    public function __construct()
    {
        $this->model = OrgStock::class;
    }


    public function handle(OrgStock $orgStock): void
    {
        OrgStockHydrateLocations::run($orgStock);
        OrgStockHydrateQuantityInLocations::run($orgStock);
        OrgStockHydrateValueInLocations::run($orgStock);
        OrgStockHydrateMovements::run($orgStock);
        OrgStockHydrateSalesIntervals::run($orgStock);
    }


}
