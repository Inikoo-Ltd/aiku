<?php

/*
 * author Arya Permana - Kirin
 * created on 21-02-2025-08h-12m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\StoredItemAudit\UI;

use App\Actions\Fulfilment\StoredItemAudit\StoreStoredItemAuditFromPallet;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Actions\Traits\Authorisations\WithWarehouseManagementEditAuthorisation;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class CreateStoredItemAuditFromPallet extends OrgAction
{
    // use WithFulfilmentShopAuthorisation; //idk what to use since we have inWarehouse
    // use WithWarehouseManagementEditAuthorisation;

    private Fulfilment|Warehouse $parent;
    private Pallet $pallet;

    public function handle(Pallet $pallet, array $modelData): StoredItemAudit
    {
        $storedItemAudit = $pallet->storedItemAudits()->where('state', StoredItemAuditStateEnum::IN_PROCESS)->first();


        if (!$storedItemAudit) {
            $storedItemAudit = StoreStoredItemAuditFromPallet::make()->action($pallet, $modelData);
        }


        return $storedItemAudit;
    }

    public function htmlResponse(StoredItemAudit $storedItemAudit, ActionRequest $request): RedirectResponse
    {
        if($this->parent instanceof Warehouse)
        {
            return Redirect::route('grp.org.warehouses.show.inventory.pallets.show.stored-item-audit.show', [
                $storedItemAudit->organisation->slug,
                $storedItemAudit->warehouse->slug,
                $storedItemAudit->scope->slug,
                $storedItemAudit->slug
            ]);
        }
        return Redirect::route('grp.org.fulfilments.show.crm.customers.show.pallets.stored-item-audits.show', [
            $storedItemAudit->organisation->slug,
            $storedItemAudit->fulfilment->slug,
            $storedItemAudit->fulfilmentCustomer->slug,
            $storedItemAudit->scope->slug,
            $storedItemAudit->slug
        ]);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inPalletInFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, Pallet $pallet, ActionRequest $request): StoredItemAudit
    {
        $this->parent = $fulfilment;
        $this->pallet = $pallet;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($pallet, $this->validatedData);
    }
    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehouse(Organisation $organisation, Warehouse $warehouse, Pallet $pallet, ActionRequest $request): StoredItemAudit
    {
        $this->parent = $warehouse;
        $this->pallet = $pallet;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($pallet, $this->validatedData);
    }


}
