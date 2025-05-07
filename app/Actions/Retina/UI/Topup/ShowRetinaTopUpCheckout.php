<?php

/*
 * author Arya Permana - Kirin
 * created on 06-05-2025-17h-01m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\UI\Topup;

use App\Actions\RetinaAction;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaTopUpCheckout extends RetinaAction
{
    public function asController(ActionRequest $request)
    {
        $this->initialisation($request);

        return $request;
    }


    public function htmlResponse(ActionRequest $request): Response
    {

        return Inertia::render(
            'Ecom/RetinaTopupCheckout',
            [
                'title'       => __('TopUp Checkout'),
                'pageHead' => [
                    'title'     =>  __('topup checkout'),
                    'icon'      => [
                        'icon'  => ['fal', 'fa-money-bill-wave'],
                        'title' => __('topup checkout')
                    ],
                ],
            ]
        );

    }
}
