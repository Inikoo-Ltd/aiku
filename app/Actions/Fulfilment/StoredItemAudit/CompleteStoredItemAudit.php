<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItemAudit;

use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePallets;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydrateStoredItemAudits;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydrateStoredItems;
use App\Actions\Fulfilment\Pallet\Hydrators\PalletHydrateStoredItems;
use App\Actions\Fulfilment\PalletStoredItem\RunPalletStoredItemQuantity;
use App\Actions\Fulfilment\StoredItem\AttachStoredItemToPallet;
use App\Actions\Fulfilment\StoredItem\Hydrators\StoreItemHydratePallets;
use App\Actions\Fulfilment\StoredItemMovement\StoreStoredItemMovement;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\PalletStoredItem\PalletStoredItemStateEnum;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Enums\Fulfilment\StoredItemAuditDelta\StoredItemAuditDeltaStateEnum;
use App\Enums\Fulfilment\StoredItemAuditDelta\StoredItemAuditDeltaTypeEnum;
use App\Http\Resources\Fulfilment\StoredItemAuditsResource;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletStoredItem;
use App\Models\Fulfilment\StoredItemAudit;
use Lorisleiva\Actions\ActionRequest;

class CompleteStoredItemAudit extends OrgAction
{
    use WithActionUpdate;

    private FulfilmentCustomer $fulfilmentCustomer;
    private StoredItemAudit $storedItemAudit;

    public function handle(StoredItemAudit $storedItemAudit, array $modelData): StoredItemAudit
    {
        foreach ($storedItemAudit->deltas as $storedItemAuditDelta) {
            $pallet = $storedItemAuditDelta->pallet;

            $storedItemAuditDelta->update(
                [
                    'state' => StoredItemAuditDeltaStateEnum::COMPLETED
                ]
            );

            if ($storedItemAuditDelta->audit_type === StoredItemAuditDeltaTypeEnum::SET_UP) {
                AttachStoredItemToPallet::run($pallet, $storedItemAuditDelta->storedItem, $storedItemAuditDelta->audited_quantity);
                $palletStoredItem = PalletStoredItem::where('pallet_id', $storedItemAuditDelta->pallet_id)->where('stored_item_id', $storedItemAuditDelta->stored_item_id)->first();
                $palletStoredItem = $this->update($palletStoredItem, [
                    'in_process' => false,
                    'state'      => PalletStoredItemStateEnum::ACTIVE
                ]);



            } else {
                $palletStoredItem = PalletStoredItem::where('pallet_id', $storedItemAuditDelta->pallet_id)->where('stored_item_id', $storedItemAuditDelta->stored_item_id)->first();

                $palletStoredItem = $this->update($palletStoredItem, [
                    'in_process' => false,
                    'state'      => $storedItemAuditDelta->audited_quantity == 0 ? PalletStoredItemStateEnum::STORED_ITEMS_MOVED_OUT : PalletStoredItemStateEnum::ACTIVE
                ]);

                $palletStoredItem->refresh();

            }

            $palletStoredItem->storedItem->update(
                [
                    'state' => StoredItemStateEnum::ACTIVE
                ]
            );

            PalletHydrateStoredItems::run($palletStoredItem->pallet);
            StoreItemHydratePallets::run($palletStoredItem->storedItem);
            StoreStoredItemMovement::run($storedItemAuditDelta);
            RunPalletStoredItemQuantity::run($palletStoredItem);
        }

        $modelData['state'] = StoredItemAuditStateEnum::COMPLETED;

        FulfilmentCustomerHydratePallets::dispatch($this->fulfilmentCustomer);
        FulfilmentCustomerHydrateStoredItems::dispatch($this->fulfilmentCustomer);
        FulfilmentCustomerHydrateStoredItemAudits::dispatch($this->fulfilmentCustomer);

        return $this->update($storedItemAudit, $modelData, ['data']);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }
        return $request->user()->authTo("fulfilment.{$this->fulfilment->id}.edit");
    }

    public function asController(FulfilmentCustomer $fulfilmentCustomer, StoredItemAudit $storedItemAudit, ActionRequest $request): StoredItemAudit
    {
        $this->fulfilmentCustomer = $storedItemAudit->fulfilmentCustomer;
        $this->storedItemAudit    = $storedItemAudit;
        $this->initialisationFromFulfilment($storedItemAudit->fulfilment, $request);

        return $this->handle($storedItemAudit, $this->validatedData);
    }

    public function action(StoredItemAudit $storedItemAudit, array $modelData): StoredItemAudit
    {
        $this->fulfilmentCustomer = $storedItemAudit->fulfilmentCustomer;
        $this->storedItemAudit    = $storedItemAudit;
        $this->asAction = true;
        $this->initialisationFromFulfilment($storedItemAudit->fulfilment, $modelData);

        return $this->handle($storedItemAudit, $this->validatedData);
    }

    public function jsonResponse(StoredItemAudit $storedItem): StoredItemAuditsResource
    {
        return new StoredItemAuditsResource($storedItem);
    }
}
