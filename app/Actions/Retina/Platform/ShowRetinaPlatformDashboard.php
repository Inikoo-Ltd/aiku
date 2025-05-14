<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-15h-01m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Platform;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Models\CRM\CustomerHasPlatform;
use App\Models\Dropshipping\Platform;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaPlatformDashboard extends RetinaAction
{
    public function asController(Platform $platform, ActionRequest $request): CustomerHasPlatform
    {
        $this->initialisationFromPlatform($platform, $request);

        return $this->customer->customerHasPlatforms()
            ->where('platform_id', $platform->id)
            ->first();
    }

    public function htmlResponse(CustomerHasPlatform $customerHasPlatform): Response
    {

        $title = __('Channel Dashboard');
        return Inertia::render('Dropshipping/Platform/PlatformDashboard', [
            'title'        => $title,
            'breadcrumbs'    => $this->getBreadcrumbs(),
            'pageHead'    => [

                'title'         => $title,
                'icon'          => [
                    'icon'  => ['fal', 'fa-tachometer-alt'],
                    'title' => $title
                ],

            ],

            'amount_shortcuts' => [],

            'platformData'  => $this->getPlatformData($customerHasPlatform),
        ]);
    }

    public function getPlatformData(CustomerHasPlatform $customerHasPlatform): array
    {
        $stats = [];

        $stats['orders'] = [
            'label'         => __('Orders'),
            'count'         => $customerHasPlatform->number_orders,
            'description'   => __('total orders'),
            'route'         => [
                'name' => 'retina.dropshipping.platforms.orders.index'
            ]
        ];

        $stats['clients'] = [
            'label'         => __('Clients'),
            'count'         => $customerHasPlatform->number_customer_clients,
            'description'   => __('total clients'),
            'route'         => [
                'name' => 'retina.dropshipping.platforms.client.index'
            ]
        ];

        $stats['portfolios'] = [
            'label'         => __('Portfolios'),
            'count'         => $customerHasPlatform->number_portfolios,
            'description'   => __('total portfolios'),
            'route'         => [
                'name' => 'retina.dropshipping.platforms.portfolios.index'
            ]
        ];

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
                                'name' => 'retina.dropshipping.platforms.dashboard'
                            ],
                            'label' => __('Channel Dashboard'),
                        ]
                    ]
                ]
            );

    }
}
