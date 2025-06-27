<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-10h-41m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Fulfilment\Order;

use App\Actions\Api\Retina\Fulfilment\Resource\PalletReturnApiResource;
use App\Actions\Fulfilment\PalletReturn\SubmitPalletReturn;
use App\Actions\RetinaApiAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Illuminate\Validation\Validator;

class SubmitApiOrder extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    public function handle(PalletReturn $palletReturn): PalletReturn
    {
        $palletReturn = SubmitPalletReturn::run($palletReturn, [], true);

        return $palletReturn;
    }

    public function afterValidator(Validator $validator)
    {
        if ($this->palletReturn->state != PalletReturnStateEnum::IN_PROCESS) {
            $validator->errors()->add('message', 'This Order is already in the "' . $this->palletReturn->state->value . '" state and cannot be updated.');
        }
    }

    public function jsonResponse(PalletReturn $palletReturn)
    {
        return PalletReturnApiResource::make($palletReturn)
            ->additional([
                'message' => __('Order submitted successfully'),
            ]);
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->palletReturn = $palletReturn;
        $this->initialisationFromFulfilment($request);
        return $this->handle($palletReturn);
    }
}
