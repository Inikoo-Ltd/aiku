<?php

/*
 * author Arya Permana - Kirin
 * created on 07-02-2025-10h-24m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\StoredItem;

use App\Actions\Fulfilment\PalletReturn\SetStoredItemReturnAutoServices;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Fulfilment\PalletStoredItem;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class AttachStoredItemToReturn extends OrgAction
{
    private PalletStoredItem $palletStoredItem;

    public function handle(PalletReturn $palletReturn, PalletStoredItem $palletStoredItem, array $modelData)
    {
        $quantityOrdered = Arr::pull($modelData, 'quantity_ordered');
        $pickingSessionId = Arr::pull($modelData, 'picking_session_id');
        $existingPalletReturnItem = PalletReturnItem::where('pallet_return_id', $palletReturn->id)
            ->where('pallet_stored_item_id', $palletStoredItem->id)
            ->first();

        if (!$pickingSessionId) {
            $pickingSessionId = PalletReturnItem::query()
                ->where('pallet_return_id', $palletReturn->id)
                ->whereNotNull('picking_session_id')
                ->value('picking_session_id');
        }

        if ($quantityOrdered == 0) {
            if ($existingPalletReturnItem) {
                $existingPalletReturnItem->delete();
            }
        } else {
            if ($existingPalletReturnItem) {
                $updateData = [
                    'quantity_ordered' => $quantityOrdered
                ];

                if ($pickingSessionId && !$existingPalletReturnItem->picking_session_id) {
                    $updateData['picking_session_id'] = $pickingSessionId;
                }

                $existingPalletReturnItem->update($updateData);
            } else {
                $palletReturn->storedItems()->attach(
                    [
                        $palletStoredItem->storedItem->id => [
                        'type'                 => 'StoredItem',
                        'pallet_id'            => $palletStoredItem->pallet_id,
                        'pallet_stored_item_id' => $palletStoredItem->id,
                        'quantity_ordered'      => $quantityOrdered,
                        'picking_location_id'   => $palletStoredItem->pallet->location_id,
                        'picking_session_id'    => $pickingSessionId,
                        ]
                    ]
                );
            }
        }
        $palletReturn = SetStoredItemReturnAutoServices::run($palletReturn);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment.{$this->fulfilment->id}.edit");
    }

    public function rules(): array
    {
        return [
            'quantity_ordered' => ['required', 'numeric', 'min:0', 'max:'.$this->palletStoredItem->quantity],
            'picking_session_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function asController(PalletReturn $palletReturn, PalletStoredItem $palletStoredItem, ActionRequest $request)
    {
        $this->palletStoredItem = $palletStoredItem;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);

        $this->handle($palletReturn, $palletStoredItem, $this->validatedData);
    }

    public function action(PalletReturn $palletReturn, PalletStoredItem|array $palletStoredItem, array $modelData = [], int $hydratorsDelay = 0)
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;

        if (is_array($palletStoredItem)) {
            $modelData = $palletStoredItem;
            $palletStoredItem = $this->resolvePalletStoredItemFromImport($palletReturn, $modelData);
        }

        $this->palletStoredItem = $palletStoredItem;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $modelData);

        $this->handle($palletReturn, $palletStoredItem, $this->validatedData);
    }

    private function resolvePalletStoredItemFromImport(PalletReturn $palletReturn, array $modelData): PalletStoredItem
    {
        $reference = Arr::get($modelData, 'reference');

        $palletStoredItem = PalletStoredItem::query()
            ->whereHas('storedItem', function ($query) use ($reference, $palletReturn) {
                $query
                    ->where('reference', $reference)
                    ->where('fulfilment_customer_id', $palletReturn->fulfilment_customer_id);
            })
            ->whereHas('pallet', function ($query) {
                $query->where('status', PalletStatusEnum::STORING);
            })
            ->orderByDesc('quantity')
            ->first();

        if (!$palletStoredItem) {
            throw ValidationException::withMessages([
                'message' => ['reference' => 'stored item does not exist'],
            ]);
        }

        return $palletStoredItem;
    }
}
