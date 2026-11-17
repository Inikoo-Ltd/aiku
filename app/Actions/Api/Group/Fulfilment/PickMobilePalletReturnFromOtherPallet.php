<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\PalletReturnItem\NotPickedPalletFromReturn;
use App\Actions\Fulfilment\PalletReturnItem\PickNewPalletReturnItem;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Fulfilment\PalletStoredItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Throwable;

class PickMobilePalletReturnFromOtherPallet
{
    use AsController;
    use WithMobilePalletReturns;

    /**
     * @throws Throwable
     */
    public function asController(Warehouse $warehouse, PalletReturn $palletReturn, PalletStoredItem $palletStoredItem, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWorkPalletReturn($user, $warehouse, $palletReturn);
        abort_unless($palletStoredItem->pallet?->warehouse_id === $warehouse->id, 404);

        $data = Validator::make($request->all(), [
            'quantity'          => ['required', 'integer', 'min:1', 'max:1000000'],
            'from_line_id'      => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'client_request_id' => ['required', 'uuid'],
        ])->validate();

        return $this->oncePerRequest(
            $user,
            $data['client_request_id'],
            fn () => $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'done', $this->changePalletReturn(
                $palletReturn,
                fn (PalletReturn $palletReturn) => $this->pickFromOtherPallet($warehouse, $palletReturn, $palletStoredItem, (int) $data['quantity'], $data['from_line_id'] ?? null)
            )),
            fn () => $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'nothing_changed', __('Already picked.'))
        );
    }

    /**
     * @throws Throwable
     */
    private function pickFromOtherPallet(Warehouse $warehouse, PalletReturn $palletReturn, PalletStoredItem $palletStoredItem, int $quantity, ?int $fromLineId): string
    {
        $this->ensureReturnState($palletReturn, [PalletReturnStateEnum::PICKING]);

        $palletStoredItem = PalletStoredItem::with(['pallet', 'storedItem'])->findOrFail($palletStoredItem->id);

        if (!$this->otherPalletStoredItemsQuery($warehouse, $palletReturn)->whereKey($palletStoredItem->id)->exists()) {
            $this->refuseState(__('This pallet can not be picked from for this return.'));
        }

        $itemLines = PalletReturnItem::where('pallet_return_id', $palletReturn->id)
            ->where('type', 'StoredItem')
            ->where('stored_item_id', $palletStoredItem->stored_item_id)
            ->get();

        $fromLine = null;
        if ($fromLineId !== null) {
            $fromLine = $itemLines->firstWhere('id', $fromLineId);
            if (!$fromLine) {
                throw ValidationException::withMessages(['from_line_id' => __('Choose a line of the same item in this return.')]);
            }
        }

        $maximum = (int) min(
            $palletStoredItem->getMaxQuantityForReturn($palletReturn),
            $itemLines->sum(fn (PalletReturnItem $line) => $line->getQuantityLeftToPick())
        );

        if ($maximum <= 0) {
            $this->refuseState(__('There is nothing left to pick of this item.'));
        }

        if ($quantity > $maximum) {
            throw ValidationException::withMessages(['quantity' => __('Only :max can be picked from this pallet.', ['max' => $maximum])]);
        }

        DB::transaction(function () use ($palletReturn, $palletStoredItem, $quantity, $fromLine) {
            PickNewPalletReturnItem::make()->handle($palletReturn, $palletStoredItem, ['quantity_ordered' => $quantity]);

            $notPicked = $fromLine ? min($quantity, $fromLine->getQuantityLeftToPick()) : 0;
            if ($notPicked > 0) {
                NotPickedPalletFromReturn::make()->handle($fromLine, ['quantity_not_picked' => (float) $fromLine->quantity_not_picked + $notPicked]);
            }
        });

        return __('Picked :quantity × :reference from :pallet.', [
            'quantity'  => $quantity,
            'reference' => $palletStoredItem->storedItem?->reference,
            'pallet'    => $palletStoredItem->pallet?->reference,
        ]);
    }
}
