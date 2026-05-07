<?php

namespace App\Actions\Fulfilment\PalletReturnItem;

use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Http\Resources\Fulfilment\MayaPalletReturnItemUIResource;
use App\Http\Resources\Fulfilment\PalletReturnItemUIResource;
use App\Models\Fulfilment\PalletReturnItem;
use Lorisleiva\Actions\ActionRequest;

class SetNotPickedPallet extends OrgAction
{
    use WithActionUpdate;

    public function handle(PalletReturnItem $palletReturnItem): PalletReturnItem
    {
        if ($palletReturnItem->type == 'Pallet') {
            $palletReturnItem = $this->update($palletReturnItem, [
                'quantity_picked'      => 0,
                'quantity_waiting_crm' => 1,
                'has_waiting_crm'      => true,
            ], ['data']);

            UpdatePallet::run($palletReturnItem->pallet, [
                'state' => PalletStateEnum::NOT_PICKED,
            ]);
        } else {
            $storedItems = PalletReturnItem::where('pallet_return_id', $palletReturnItem->pallet_return_id)
                ->where('stored_item_id', $palletReturnItem->stored_item_id)
                ->get();

            foreach ($storedItems as $storedItem) {
                $palletReturnItem = $this->update($storedItem, [
                    'quantity_picked'      => 0,
                    'quantity_waiting_crm' => $storedItem->quantity_ordered,
                    'has_waiting_crm'      => true,
                ], ['data']);

                UpdatePallet::run($storedItem->pallet, [
                    'state' => PalletStateEnum::NOT_PICKED,
                ]);
            }
        }

        $palletReturn = $palletReturnItem->palletReturn;
        if ($palletReturn) {
            $palletReturn->update([
                'number_items_waiting_crm' => $palletReturn->items()->where('has_waiting_crm', true)->count(),
            ]);
        }

        return $palletReturnItem;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment.{$this->warehouse->id}.edit");
    }

    public function asController(PalletReturnItem $palletReturnItem, ActionRequest $request): PalletReturnItem
    {
        $this->initialisationFromWarehouse($palletReturnItem->palletReturn->warehouse, $request);

        return $this->handle($palletReturnItem);
    }

    public function jsonResponse(PalletReturnItem $palletReturnItem, ActionRequest $request): PalletReturnItemUIResource|MayaPalletReturnItemUIResource
    {
        if ($request->hasHeader('Maya-Version')) {
            return MayaPalletReturnItemUIResource::make($palletReturnItem);
        }

        return new PalletReturnItemUIResource($palletReturnItem);
    }
}
