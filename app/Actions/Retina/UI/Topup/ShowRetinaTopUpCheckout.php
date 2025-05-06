<?php
/*
 * author Arya Permana - Kirin
 * created on 06-05-2025-17h-01m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\UI\Topup;

use App\Actions\RetinaAction;
use App\Enums\UI\CRM\CustomerClientTabsEnum;
use App\Enums\UI\CRM\CustomerTabsEnum;
use App\Http\Resources\CRM\CustomerClientResource;
use App\Models\Accounting\TopUp;
use App\Models\Dropshipping\CustomerClient;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaTopUpCheckout extends RetinaAction
{
    public function handle(TopUp $topUp): TopUp
    {
        return $topUp;
    }

    public function asController(
        TopUp $topUp,
        ActionRequest $request
    ): TopUp {
        $this->initialisation($request);

        return $this->handle($topUp);
    }


    public function htmlResponse(TopUp $topUp, ActionRequest $request): Response
    {

        return Inertia::render(
            'Ecom/RetinaTopupCheckout',
            [
                'title'       => __('TopUp Checkout'),
                'pageHead' => [
                    'title'     => $topUp->reference,
                    'icon'      => [
                        'icon'  => ['fal', 'fa-money-bill-wave'],
                        'title' => __('topup checkout')
                    ],
                ],
            ]
        );

    }
}
