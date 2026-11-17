<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\Pallet\PickWholePalletInPalletReturn;
use App\Actions\Fulfilment\PalletReturnItem\PickPalletReturnItemInPalletReturnWithStoredItem;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Throwable;

class PickMobilePalletReturnLine
{
    use AsController;
    use WithMobilePalletReturns;

    /**
     * @throws Throwable
     */
    public function asController(Warehouse $warehouse, PalletReturn $palletReturn, PalletReturnItem $palletReturnItem, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWorkPalletReturn($user, $warehouse, $palletReturn);
        $this->ensureLineOfReturn($palletReturn, $palletReturnItem);

        if ($palletReturnItem->type === 'Pallet') {
            $message = $this->changePalletReturn($palletReturn, function (PalletReturn $palletReturn) use ($palletReturnItem, $user) {
                $line = $this->lineReadyFor($palletReturn, $palletReturnItem, 'pick');
                PickWholePalletInPalletReturn::make()->action($line, [], user: $user);

                return __('Pallet :reference picked.', ['reference' => $line->pallet?->reference]);
            });

            return $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'done', $message);
        }

        $data = Validator::make($request->all(), [
            'quantity'          => ['required_without:all', 'prohibits:all', 'integer', 'min:1', 'max:1000000'],
            'all'               => ['sometimes', 'accepted'],
            'client_request_id' => ['required', 'uuid'],
        ])->validate();

        return $this->oncePerRequest(
            $user,
            $data['client_request_id'],
            fn () => $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'done', $this->changePalletReturn(
                $palletReturn,
                fn (PalletReturn $palletReturn) => $this->pickStoredItemLine($this->lineReadyFor($palletReturn, $palletReturnItem, 'pick'), $user, isset($data['quantity']) ? (int) $data['quantity'] : null)
            )),
            fn () => $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'nothing_changed', __('Already picked.'))
        );
    }

    /**
     * @throws Throwable
     */
    private function pickStoredItemLine(PalletReturnItem $line, User $user, ?int $quantity): string
    {
        $quantityToPick   = $line->getQuantityLeftToPick();
        $quantityOnPallet = $this->quantityOnPallet($line);
        $quantity         = $quantity === null ? min($quantityToPick, $quantityOnPallet) : (float) $quantity;

        if ($quantity > $quantityToPick) {
            throw ValidationException::withMessages(['quantity' => __('Only :max left to pick on this line.', ['max' => (int) $quantityToPick])]);
        }

        if ($quantity > $quantityOnPallet) {
            throw ValidationException::withMessages(['quantity' => __('Only :max left on this pallet.', ['max' => (int) $quantityOnPallet])]);
        }

        PickPalletReturnItemInPalletReturnWithStoredItem::make()->handle($line, ['quantity_picked' => (float) $line->quantity_picked + $quantity], $user);

        return __('Picked :quantity × :reference.', ['quantity' => (int) $quantity, 'reference' => $line->storedItem?->reference]);
    }
}
