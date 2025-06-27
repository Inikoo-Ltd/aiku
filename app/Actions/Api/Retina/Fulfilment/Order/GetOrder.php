<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-10h-24m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Fulfilment\Order;

use App\Actions\Api\Retina\Fulfilment\Resource\PalletReturnApiResource;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetOrder
{
    use AsAction;
    use WithAttributes;

    public function handle(PalletReturn $palletReturn): PalletReturn
    {
        return $palletReturn;
    }

    public function jsonResponse(PalletReturn $palletReturn)
    {
        return PalletReturnApiResource::make($palletReturn);
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        return $this->handle($palletReturn);
    }
}
