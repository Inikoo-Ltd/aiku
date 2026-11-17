<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\PalletReturnItem\NotPickedPalletFromReturn;
use App\Actions\Fulfilment\PalletReturnItem\SetNotPickedPallet;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class SetMobilePalletReturnLineAsNotPicked
{
    use AsController;
    use WithMobilePalletReturns;

    /**
     * @throws ValidationException
     */
    public function asController(Warehouse $warehouse, PalletReturn $palletReturn, PalletReturnItem $palletReturnItem, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWorkPalletReturn($user, $warehouse, $palletReturn);
        $this->ensureLineOfReturn($palletReturn, $palletReturnItem);

        if ($palletReturnItem->type === 'Pallet') {
            $message = $this->changePalletReturn($palletReturn, function (PalletReturn $palletReturn) use ($palletReturnItem) {
                $line = $this->lineReadyFor($palletReturn, $palletReturnItem, 'not_picked');
                SetNotPickedPallet::make()->handle($line);

                return __('Pallet :reference set as not picked. Customer service must resolve it before the return can be set as picked.', ['reference' => $line->pallet?->reference]);
            });

            return $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'done', $message);
        }

        $data = Validator::make($request->all(), [
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000000'],
        ])->validate();

        $message = $this->changePalletReturn($palletReturn, function (PalletReturn $palletReturn) use ($palletReturnItem, $data) {
            $line           = $this->lineReadyFor($palletReturn, $palletReturnItem, 'not_picked');
            $quantityToPick = $line->getQuantityLeftToPick();
            $quantity       = isset($data['quantity']) ? (float) $data['quantity'] : $quantityToPick;

            if ($quantity > $quantityToPick) {
                throw ValidationException::withMessages(['quantity' => __('Only :max left to pick on this line.', ['max' => (int) $quantityToPick])]);
            }

            NotPickedPalletFromReturn::make()->handle($line, ['quantity_not_picked' => (float) $line->quantity_not_picked + $quantity]);

            return __(':quantity × :reference set as not picked.', ['quantity' => (int) $quantity, 'reference' => $line->storedItem?->reference]);
        });

        return $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'done', $message);
    }
}
