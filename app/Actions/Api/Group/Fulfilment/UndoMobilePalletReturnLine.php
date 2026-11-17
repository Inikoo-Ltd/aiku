<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\PalletReturnItem\UndoPickingPalletFromReturn;
use App\Actions\Fulfilment\PalletReturnItem\UndoStoredItemPick;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UndoMobilePalletReturnLine
{
    use AsController;
    use WithMobilePalletReturns;

    public function asController(Warehouse $warehouse, PalletReturn $palletReturn, PalletReturnItem $palletReturnItem, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWorkPalletReturn($user, $warehouse, $palletReturn);
        $this->ensureLineOfReturn($palletReturn, $palletReturnItem);

        $message = $this->changePalletReturn($palletReturn, function (PalletReturn $palletReturn) use ($palletReturnItem) {
            $line = $this->lineReadyFor($palletReturn, $palletReturnItem, 'undo');

            if ($line->type === 'Pallet') {
                UndoPickingPalletFromReturn::make()->handle($line);

                return __('Pallet :reference is back to picking.', ['reference' => $line->pallet?->reference]);
            }

            UndoStoredItemPick::make()->handle($line);

            return __(':reference on :pallet is back to picking.', ['reference' => $line->storedItem?->reference, 'pallet' => $line->pallet?->reference]);
        });

        return $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'done', $message);
    }
}
