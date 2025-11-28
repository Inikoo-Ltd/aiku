<?php

/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\Dropshipping\Ebay\Traits\WithEbayApiRequest;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ShowCallbackSuccessRetinaEbayUser extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithEbayApiRequest;

    public function htmlResponse(ActionRequest $request): Response
    {
        return Inertia::render('Dropshipping/ShowCallbackSuccessRetinaEbay', []);
    }

    public function asController(ActionRequest $request): ActionRequest
    {
        $this->initialisation($request);

        return $request;
    }
}
