<?php

/*
 * author Arya Permana - Kirin
 * created on 25-06-2025-15h-20m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Fulfilment\Order;

use App\Actions\Fulfilment\PalletReturn\CancelPalletReturn;
use App\Actions\RetinaApiAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class CancelApiOrder extends RetinaApiAction
{
    public function handle(PalletReturn $palletReturn): PalletReturn
    {
        CancelPalletReturn::run($palletReturn, []);
        return $palletReturn;
    }

    public function afterValidator($validator)
    {
        if ($this->palletReturn->state != PalletReturnStateEnum::SUBMITTED) {
            $validator->errors()->add('message', 'This Order is already in the "' . $this->palletReturn->state->value . '" state and cannot be updated.');
        }
    }


    public function jsonResponse(PalletReturn $palletReturn): JsonResponse
    {
        return response()->json([
            'message' => __('Order cancelled successfully'),
            'pallet_return_id' => $palletReturn->id,
        ]);
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->initialisationFromFulfilment($request);
        return $this->handle($palletReturn);
    }
}
