<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 20 Jun 2024 10:36:39 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturnItem;

use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Fulfilment\PalletReturn\UpdatePalletReturn;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Http\Resources\Fulfilment\PalletReturnItemUIResource;
use App\Models\Fulfilment\PalletReturnItem;
use Lorisleiva\Actions\ActionRequest;

class UndoPalletReturnItem extends OrgAction
{
    use WithActionUpdate;


    private PalletReturnItem $palletReturnItem;

    public function handle(PalletReturnItem $palletReturnItem): PalletReturnItem
    {
        $modelData['state'] = PalletReturnItemStateEnum::SUBMITTED;

        $newPalletState = match ($palletReturnItem->pallet->state) {
            PalletStateEnum::PICKED => PalletStateEnum::PICKING,
            PalletStateEnum::REQUEST_RETURN_IN_PROCESS, PalletStateEnum::REQUEST_RETURN_SUBMITTED, PalletStateEnum::REQUEST_RETURN_CONFIRMED => PalletStateEnum::STORING,
            default => null
        };

        if ($palletReturnItem->type == 'Pallet' && $newPalletState) {
            UpdatePallet::run($palletReturnItem->pallet, [
                'state' => $newPalletState,
                'requested_for_return_at' => null
            ]);

            $palletReturnItem = $this->update($palletReturnItem, $modelData, ['data']);
        } elseif ($newPalletState) {
            $storedItems = PalletReturnItem::where('pallet_return_id', $palletReturnItem->pallet_return_id)->where('stored_item_id', $palletReturnItem->stored_item_id)->get();
            foreach ($storedItems as $storedItem) {
                UpdatePallet::run($storedItem->pallet, [
                    'state' => $newPalletState
                ]);

                $palletReturnItem = $this->update($storedItem, $modelData, ['data']);
            }
        }

        if ($palletReturnItem->palletReturn->state == PalletReturnStateEnum::CONFIRMED) {
            UpdatePalletReturn::run($palletReturnItem->palletReturn, [
                'state' => PalletReturnStateEnum::SUBMITTED
            ]);
        }

        return $palletReturnItem;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }
        return $request->user()->authTo([
            "fulfilment.{$this->warehouse->id}.edit",
            "supervisor-incoming.{$this->warehouse->id}",
            "supervisor-fulfilment.{$this->warehouse->id}",
        ]);
    }

    public function asController(PalletReturnItem $palletReturnItem, ActionRequest $request): PalletReturnItem
    {
        $this->initialisationFromWarehouse($palletReturnItem->palletReturn->warehouse, $request);

        return $this->handle($palletReturnItem);
    }

    public function action(PalletReturnItem $palletReturnItem, $state, int $hydratorsDelay = 0): PalletReturnItem
    {
        $this->asAction = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromWarehouse($palletReturnItem->palletReturn->warehouse, []);

        return $this->handle($palletReturnItem);
    }

    public function jsonResponse(PalletReturnItem $palletReturnItem): PalletReturnItemUIResource
    {
        return new PalletReturnItemUIResource($palletReturnItem);
    }
}
