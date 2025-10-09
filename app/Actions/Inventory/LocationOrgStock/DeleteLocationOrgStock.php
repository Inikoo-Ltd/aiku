<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Aug 2024 22:35:34 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Actions\Inventory\Location\Hydrators\LocationHydrateStocks;
use App\Actions\Inventory\Location\Hydrators\LocationHydrateStockValue;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateLocations;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Actions\Maintenance\Dispatching\RepairOrgStockMissingLocationIds;
use App\Actions\OrgAction;
use App\Models\Inventory\LocationOrgStock;
use Lorisleiva\Actions\ActionRequest;

class DeleteLocationOrgStock extends OrgAction
{
    use WithLocationOrgStockActionAuthorisation;


    public function handle(LocationOrgStock $locationOrgStock): void
    {
        $location = $locationOrgStock->location;
        $orgStock = $locationOrgStock->orgStock;

        $locationOrgStock->delete();

        RepairOrgStockMissingLocationIds::dispatch($orgStock);
        LocationHydrateStocks::dispatch($location);
        LocationHydrateStockValue::dispatch($location);
        OrgStockHydrateLocations::dispatch($orgStock);
        OrgStockHydrateQuantityInLocations::dispatch($orgStock);
    }

    public function asController(LocationOrgStock $locationOrgStock, ActionRequest $request): void
    {
        $this->initialisation($locationOrgStock->organisation, $request);
        $this->handle($locationOrgStock);
    }

    public function action(LocationOrgStock $locationOrgStock): void
    {
        $this->asAction = true;
        $this->initialisation($locationOrgStock->organisation, []);

        $this->handle($locationOrgStock);
    }

}
