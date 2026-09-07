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

    /**
     * WooCommerce sends the owner back here with success=0 when its own POST of the keys to our
     * callback failed, so the page must not claim the store is connected in that case.
     */
    public function htmlResponse(ActionRequest $request): Response
    {
        return Inertia::render('Dropshipping/ShowCallbackSuccessRetinaEbay', [
            'success' => $request->query('success', '1') !== '0',
        ]);
    }

    public function asController(ActionRequest $request): ActionRequest
    {
        $this->initialisation($request);

        return $request;
    }
}
