<?php
/*
 * author Arya Permana - Kirin
 * created on 09-05-2025-10h-20m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Accounting\MitSavedCard\UI;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerHasPlatform;
use App\Models\Dropshipping\Platform;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaCreditCardDashboard extends RetinaAction
{
    public function asController(ActionRequest $request): Customer
    {
        $this->initialisation($request);
        return $this->customer;
    }

    public function htmlResponse(Customer $customer): Response
    {

        $title = __('Credit Card Dashboard');
        return Inertia::render('Dropshipping/DashboardRetinaCreditCard', [
            'title'        => $title,
            'breadcrumbs'    => $this->getBreadcrumbs(),
            'pageHead'    => [

                'title'         => $title,
                'icon'          => [
                    'icon'  => ['fal', 'fa-tachometer-alt'],
                    'title' => $title
                ],
                'actions'       => [
                    [
                        'type'  => 'button',
                        'style' => 'create',
                        'label' => __('Save Credit Card'),
                        'route' => [
                            'name'       => 'retina.dropshipping.saved-credit-card.show',
                        ],
                    ]
                ]

            ],

            'creditCardData'  => $this->getcreditCardData($customer),

            'delete_route' => [
                'name' => 'retina.models.mit-saved-card.delete',
                'method' => 'delete',
            ]
        ]);
    }

    public function getcreditCardData(Customer $customer): array
    {
        $stats = [];

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
                                'name' => 'retina.dropshipping.saved-credit-card.dashboard'
                            ],
                            'label' => __('Credit Card Dashboard'),
                        ]
                    ]
                ]
            );

    }
}
