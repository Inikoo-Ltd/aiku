<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 01:01:48 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Actions\Inventory\OrgStockAuditDelta\StoreOrgStockAuditDelta;
use App\Actions\Maintenance\Dispatching\RepairOrgStockMissingLocationIds;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Inventory\LocationOrgStock;
use Lorisleiva\Actions\ActionRequest;

class AuditLocationOrgStock extends OrgAction
{
    use WithActionUpdate;
    use WithLocationOrgStockActionAuthorisation;



    private LocationOrgStock $locationOrgStock;

    public function handle(LocationOrgStock $locationOrgStock, array $modelData): LocationOrgStock
    {

        data_set($modelData, 'audited_at', now());

        $currentStock    = $locationOrgStock->quantity;
        $locationOrgStock = $this->update($locationOrgStock, $modelData);
        $newStock        = $locationOrgStock->quantity;
        $stockDiff       = $newStock - $currentStock;

        StoreOrgStockAuditDelta::make()->action($locationOrgStock, [
            'original_quantity' => $newStock,
            'audited_quantity'  => $stockDiff,
        ]);
        RepairOrgStockMissingLocationIds::dispatch($locationOrgStock->orgStock);
        OrgStockHydrateQuantityInLocations::dispatch($locationOrgStock->orgStock);

        return $locationOrgStock;
    }

    public function rules(): array
    {
        return [
            'quantity' => [ 'required','numeric','gt:0'],
        ];
    }


    public function prepareForValidation(): void
    {

        if (!$this->has('quantity')) {
            $this->set('quantity', $this->locationOrgStock->quantity);
        }

    }

    public function action(LocationOrgStock $locationOrgStock, array $modelData): LocationOrgStock
    {
        $this->asAction        = true;
        $this->locationOrgStock = $locationOrgStock;
        $this->initialisation($locationOrgStock->organisation, $modelData);

        return $this->handle($locationOrgStock, $this->validatedData);
    }

    public function asController(LocationOrgStock $locationOrgStock, ActionRequest $request): LocationOrgStock
    {
        $this->locationOrgStock = $locationOrgStock;
        $this->initialisation($locationOrgStock->organisation, $request);

        return $this->handle($locationOrgStock, $this->validatedData);
    }
}
