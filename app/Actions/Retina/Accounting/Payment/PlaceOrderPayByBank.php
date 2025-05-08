<?php

/*
 * author Arya Permana - Kirin
 * created on 07-05-2025-15h-53m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Accounting\Payment;

use App\Actions\RetinaAction;
use Lorisleiva\Actions\ActionRequest;

class PlaceOrderPayByBank extends RetinaAction
{
    public function handle()
    {
        return dd('hello');
    }

    public function asController(ActionRequest $request): void
    {
        $this->initialisation($request);
        $this->handle();
    }

}
