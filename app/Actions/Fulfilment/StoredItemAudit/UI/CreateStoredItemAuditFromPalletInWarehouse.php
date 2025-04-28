<?php

/*
 * author Arya Permana - Kirin
 * created on 21-02-2025-08h-12m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\StoredItemAudit\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithFulfilmentWarehouseEditAuthorisation;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class CreateStoredItemAuditFromPalletInWarehouse extends OrgAction
{
    use WithFulfilmentWarehouseEditAuthorisation;

    private Fulfilment|Warehouse $parent;
    private Pallet $pallet;

    public function handle(Pallet $pallet, array $modelData): StoredItemAudit
    {
        return CreateStoredItemAuditFromPallet::run($modelData, $pallet);
    }

    public function htmlResponse(StoredItemAudit $storedItemAudit, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.inventory.pallets.show.stored-item-audit.show', [
            $storedItemAudit->organisation->slug,
            $storedItemAudit->warehouse->slug,
            $storedItemAudit->fulfilment->slug,
            $storedItemAudit->slug
        ]);
    }


    public function asController(Organisation $organisation, Warehouse $warehouse, Pallet $pallet, ActionRequest $request): StoredItemAudit
    {
        $this->parent = $warehouse;
        $this->pallet = $pallet;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($pallet, $this->validatedData);
    }


}
