<?php
/*
 * author Arya Permana - Kirin
 * created on 06-05-2025-14h-58m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\UI\Topup;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Accounting\TopUp\TopUpStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Http\Resources\Catalogue\RetinaRentalAgreementResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\FulfilmentCustomer;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaTopUpDashboard extends RetinaAction
{
    public function asController(ActionRequest $request): Customer
    {
        $this->initialisation($request);
        return $this->customer;
    }

    public function htmlResponse(Customer $customer): Response
    {
        $routeActions = [];

        $routeActions[] = [
            'type'    => 'button',
            'style'   => 'create',
            'tooltip' =>  __('Make a new topup'),
            'label'   => __('New TopUp'),
            'route'   => [
                'method'     => 'get',
                'name'       => 'retina.topup.create',
            ]
        ];

        $routeActions = array_filter($routeActions);

        return Inertia::render('Storage/RetinaTopUpDashboard', [
            'title'        => __('TopUp Dashboard'),
            'breadcrumbs'    => $this->getBreadcrumbs(),
            'pageHead'    => [

                'title'         => __('TopUp Dashboard'),
                'icon'          => [
                    'icon'  => ['fal', 'fa-tachometer-alt'],
                    'title' => __('TopUp Dashboard')
                ],

            ],

            'route_action' => $routeActions,

            'currency'     => CurrencyResource::make($customer->shop->currency),
            'topUpData'  => $this->getTopUpData($customer),
        ]);
    }

    public function getTopUpData(Customer $customer): array
    {
        $stats = [];

        $stats['topUps'] = [
            'label'         => __('TopUps'),
            'count'         => $customer->stats->number_top_ups,
            'description'   => __('number of top ups'),
            'route'         => [
                'name' => 'retina.topup.index'
            ]
        ];

        // foreach (TopUpStatusEnum::cases() as $case) {
        //     $stats['topUps']['status'][$case->value] = [
        //         'value' => $case->value,
        //         'icon'  => TopUpStatusEnum::stateIcon()[$case->value],
        //         'count' => TopUpStatusEnum::count($fulfilmentCustomer)[$case->value] ?? 0,
        //         'label' => TopUpStatusEnum::labels()[$case->value]
        //     ];
        // }
        return $stats;
    }

    public function getBreadcrumbs(): array
    {

        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.topup.dashboard'
                            ],
                            'label' => __('TopUp'),
                        ]
                    ]
                ]
            );

    }
}
