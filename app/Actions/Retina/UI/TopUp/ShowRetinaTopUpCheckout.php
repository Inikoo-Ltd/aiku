<?php

/*
 * author Arya Permana - Kirin
 * created on 06-05-2025-17h-01m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\UI\TopUp;

use App\Actions\RetinaAction;
use App\Models\Accounting\TopUpPaymentApiPoint;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaTopUpCheckout extends RetinaAction
{
    public function asController(TopUpPaymentApiPoint $topUpPaymentApiPoint, ActionRequest $request): ActionRequest
    {
        $this->initialisation($request);
        return $request;
    }


    public function htmlResponse(ActionRequest $request): Response
    {

        $title = __('Top Up Checkout');
        return Inertia::render(
            'Ecom/RetinaTopUpCheckout',
            [
                'title'       => $title,
                'pageHead' => [
                    'title'     =>  $title,
                    'icon'      => [
                        'icon'  => ['fal', 'fa-money-bill-wave'],
                        'title' => $title
                    ],
                ],
            ]
        );

    }
}
