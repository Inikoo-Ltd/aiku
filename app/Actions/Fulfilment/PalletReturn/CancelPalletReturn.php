<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\PickingSession\AutoFinishPackingFulfilmentPickingSession;
use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePalletReturns;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletReturns;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePallets;
use App\Actions\Fulfilment\PalletReturn\Notifications\SendPalletReturnNotification;
use App\Actions\Fulfilment\PalletReturn\Search\PalletReturnRecordSearch;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePalletReturns;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletReturns;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePalletReturns;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Http\Resources\Fulfilment\PalletReturnResource;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Fulfilment\PalletReturnItem;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Actions\Fulfilment\PalletReturnItem\UndoPickingPalletFromReturn;
use App\Models\Fulfilment\PalletStoredItem;
use App\Models\Fulfilment\StoredItemMovement;
use App\Enums\Fulfilment\PalletStoredItem\PalletStoredItemStateEnum;

class CancelPalletReturn extends OrgAction
{
    use WithActionUpdate;


    public function handle(PalletReturn $palletReturn, array $modelData): PalletReturn
    {
        $palletReturn = DB::transaction(function () use ($palletReturn, $modelData) {

            $modelData[PalletReturnStateEnum::CANCEL->value.'_at']    = now();
            $modelData['state']                                       = PalletReturnStateEnum::CANCEL;

            if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
                $palletItems = PalletReturnItem::where('pallet_return_id', $palletReturn->id)
                    ->where('type', 'Pallet')
                    ->get();

                foreach ($palletItems as $palletReturnItem) {
                    if ($palletReturnItem->state === PalletReturnItemStateEnum::PICKED) {
                        UndoPickingPalletFromReturn::run($palletReturnItem);
                    }
                }
            }

            if ($palletReturn->type == PalletReturnTypeEnum::STORED_ITEM) {
                StoredItemMovement::where('pallet_return_id', $palletReturn->id)->delete();
                $palletReturn->storedItems->each(function ($storedItem) {
                    $palletStoredItem = PalletStoredItem::find($storedItem->pivot->pallet_stored_item_id);
                    $storedItem->increment('total_quantity', (float) $storedItem->pivot->quantity_picked);
                    if ($palletStoredItem) {
                        $palletStoredItem->increment('quantity', (float) $storedItem->pivot->quantity_picked);
                        if ($palletStoredItem->state === PalletStoredItemStateEnum::RETURNED) {
                            $palletStoredItem->update([
                                'state' => PalletStoredItemStateEnum::ACTIVE,
                            ]);
                        }
                    }
                });
            }

            $palletReturn->pallets()->update([
                'status'            => PalletStatusEnum::STORING,
                'state'             => PalletStateEnum::STORING,
                'pallet_return_id'  => null,
                'set_as_incident_at' => null,
                'incident_report'   => [],
            ]);
            $palletReturn = $this->update($palletReturn, $modelData);
            $palletReturn->pallets()->updateExistingPivot($palletReturn->pallets->pluck('id'), [
                'state'  => PalletReturnStateEnum::CANCEL
            ]);
            $palletReturn->refresh();


            GroupHydratePalletReturns::dispatch($palletReturn->group);
            OrganisationHydratePalletReturns::dispatch($palletReturn->organisation);
            WarehouseHydratePalletReturns::dispatch($palletReturn->warehouse);
            FulfilmentCustomerHydratePalletReturns::dispatch($palletReturn->fulfilmentCustomer);
            FulfilmentCustomerHydratePallets::dispatch($palletReturn->fulfilmentCustomer);
            FulfilmentHydratePalletReturns::dispatch($palletReturn->fulfilment);

            SendPalletReturnNotification::run($palletReturn);
            PalletReturnRecordSearch::dispatch($palletReturn);

            return $palletReturn;

        });

        $pickingSessions = $palletReturn->pickingSessions()->get();
        foreach ($pickingSessions as $pickingSession) {
            (new AutoFinishPackingFulfilmentPickingSession())->action($pickingSession);
        }

        return $palletReturn;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function jsonResponse(PalletReturn $palletReturn): JsonResource
    {
        return new PalletReturnResource($palletReturn);
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {

        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);

        return $this->handle($palletReturn, $this->validatedData);
    }

    public function action(FulfilmentCustomer $fulfilmentCustomer, PalletReturn $palletReturn): PalletReturn
    {
        $this->asAction = true;
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, []);

        return $this->handle($palletReturn, $this->validatedData);
    }
}
