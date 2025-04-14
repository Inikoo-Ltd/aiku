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
use App\Actions\Traits\Authorisations\WithFulfilmentShopEditAuthorisation;
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
    use WithFulfilmentShopEditAuthorisation;

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
        return Redirect::route('grp.org.fulfilments.show.crm.customers.show.pallets.stored-item-audits.show', [
            $storedItemAudit->organisation->slug,
            $storedItemAudit->fulfilment->slug,
            $storedItemAudit->fulfilmentCustomer->slug,
            $this->pallet,
            $storedItemAudit->slug
        ]);
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, Pallet $pallet, ActionRequest $request): StoredItemAudit
    {
        $this->parent = $fulfilment;
        $this->pallet = $pallet;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($pallet, $this->validatedData);
    }


}
