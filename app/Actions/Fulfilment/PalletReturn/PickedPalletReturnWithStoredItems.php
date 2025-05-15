<?php

/*
 * author Arya Permana - Kirin
 * created on 07-02-2025-16h-48m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePalletReturns;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletReturns;
use App\Actions\Fulfilment\PalletReturn\Notifications\SendPalletReturnNotification;
use App\Actions\Fulfilment\PalletReturn\Search\PalletReturnRecordSearch;
use App\Actions\Fulfilment\StoredItemMovement\StoreStoredItemMovementFromPicking;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePalletReturns;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletReturns;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePalletReturns;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Http\Resources\Fulfilment\PalletReturnResource;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class PickedPalletReturnWithStoredItems extends OrgAction
{
    use WithActionUpdate;


    public function handle(PalletReturn $palletReturn, array $modelData = []): PalletReturn
    {
        if ($palletReturn->type != PalletReturnTypeEnum::STORED_ITEM) {
            abort(419);
        }
        return DB::transaction(function () use ($palletReturn, $modelData) {
            $modelData[PalletReturnStateEnum::PICKED->value . '_at'] = now();
            $modelData['state'] = PalletReturnStateEnum::PICKED;

            $palletReturn = $this->update($palletReturn, $modelData);


            foreach ($palletReturn->storedItems as $storedItem) {


                $palletReturnItem = PalletReturnItem::find($storedItem->pivot->id);
                if ($palletReturnItem->state == PalletReturnItemStateEnum::PICKING) {
                    $quantityPicked = $palletReturnItem->quantity_ordered;
                    $palletReturnItem->update([
                        'quantity_picked' => $quantityPicked
                    ]);
                    StoreStoredItemMovementFromPicking::run($palletReturnItem, [
                        'quantity' => $quantityPicked
                    ]);

                }


            }
            SetStoredItemReturnAutoServices::run($palletReturn, true);
            GroupHydratePalletReturns::dispatch($palletReturn->group);
            OrganisationHydratePalletReturns::dispatch($palletReturn->organisation);
            WarehouseHydratePalletReturns::dispatch($palletReturn->warehouse);
            FulfilmentCustomerHydratePalletReturns::dispatch($palletReturn->fulfilmentCustomer);
            FulfilmentHydratePalletReturns::dispatch($palletReturn->fulfilment);

            SendPalletReturnNotification::run($palletReturn);
            PalletReturnRecordSearch::dispatch($palletReturn);

            return $palletReturn;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }
        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    // public function jsonResponse(PalletReturn $palletReturn): JsonResource
    // {
    //     return new PalletReturnResource($palletReturn);
    // }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);

        return $this->handle($palletReturn, $this->validatedData);
    }

    // public function maya(PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    // {
    //     $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);

    //     return $this->handle($palletReturn, $this->validatedData);
    // }

    // public function action(FulfilmentCustomer $fulfilmentCustomer, PalletReturn $palletReturn): PalletReturn
    // {
    //     $this->asAction = true;
    //     $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, []);

    //     return $this->handle($palletReturn, $this->validatedData);
    // }
}
