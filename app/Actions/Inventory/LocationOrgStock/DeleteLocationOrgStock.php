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
use App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockCurrentStockHistories;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\Maintenance\Dispatching\RepairOrgStockMissingLocationIds;
use App\Actions\OrgAction;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\LocationOrgStock;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class DeleteLocationOrgStock extends OrgAction
{
    use WithLocationOrgStockActionAuthorisation;


    /**
     * @throws \Throwable
     */
    public function handle(LocationOrgStock $locationOrgStock): void
    {
        $location = $locationOrgStock->location;
        $orgStock = $locationOrgStock->orgStock;

        DB::transaction(function () use ($locationOrgStock, $location, $orgStock) {
            StoreOrgStockMovement::make()->action(
                $orgStock,
                $location,
                [
                    'quantity' => 0,
                    'org_amount' => 0,
                    'date' => now()->format('Y-m-d H:i:s.u'),
                    'type' => OrgStockMovementTypeEnum::DISASSOCIATE,
                ]
            );

            $locationOrgStock->delete();

            return $locationOrgStock;
        });


        RepairOrgStockMissingLocationIds::dispatch($orgStock);
        LocationHydrateStocks::dispatch($location);
        LocationHydrateStockValue::dispatch($location);
        OrgStockHydrateLocations::dispatch($orgStock);
        OrgStockHydrateQuantityInLocations::dispatch($orgStock);
        CalculateOrgStockCurrentStockHistories::dispatch($orgStock->id);
    }

    /**
     * @throws \Throwable
     */
    public function asController(LocationOrgStock $locationOrgStock, ActionRequest $request): void
    {
        $this->initialisation($locationOrgStock->organisation, $request);
        $this->handle($locationOrgStock);
    }

    /**
     * @throws \Throwable
     */
    public function action(LocationOrgStock $locationOrgStock): void
    {
        $this->asAction = true;
        $this->initialisation($locationOrgStock->organisation, []);

        $this->handle($locationOrgStock);
    }

}
