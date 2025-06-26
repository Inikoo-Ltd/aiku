<?php

/*
 * author Arya Permana - Kirin
 * created on 25-06-2025-15h-20m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Fulfilment\Order;

use App\Actions\Fulfilment\PalletReturn\DeletePalletReturn;
use App\Actions\RetinaApiAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class DeleteApiOrder extends RetinaApiAction
{
    public function handle(PalletReturn $palletReturn, array $modelData): JsonResponse
    {

        if ($palletReturn->state != PalletReturnStateEnum::IN_PROCESS) {
            return response()->json([
                'message' => 'You can not delete this pallet return',
            ]);
        } else {

            DeletePalletReturn::make()->action($palletReturn, $modelData);
            return response()->json([
                'message' => 'Pallet return deleted successfully',
                'PalletReturn_id' => $palletReturn->id
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'deleted_note' => ['required', 'string', 'max:4000'],
        ];
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): JsonResponse
    {
        $this->initialisationFromFulfilment($request);
        return $this->handle($palletReturn, $this->validatedData);
    }
}
