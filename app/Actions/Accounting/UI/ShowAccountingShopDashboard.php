<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 24-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Comms\Traits\WithAccountingSubNavigation;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAccountingShopDashboard extends OrgAction
{
    use WithAccountingSubNavigation;


    public function handle(Shop|Fulfilment $parent): Shop|Fulfilment
    {
        return $parent;
    }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): Fulfilment
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }


    public function htmlResponse(Shop|Fulfilment $parent, ActionRequest $request): Response
    {
        if ($parent instanceof Shop) {
            $subNavigation = $this->getSubNavigationShop($parent);
        } else { //Fulfilment
            $subNavigation = $this->getSubNavigation($parent);
        }

        return Inertia::render(
            'Org/Shop/AccountingShopDashboard',
            [
                'breadcrumbs'  => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'        => __('Accounting'),
                'pageHead'     => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-coins'],
                        'title' => __('Accounting')
                    ],
                    'iconRight'     => [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'title' => __('dashboard')
                    ],
                    'title'         => __('Accounting dashboard'),
                    'subNavigation' => $subNavigation,
                ],
                'flatTreeMaps' => [
                    [


                        [
                            'name'  => __('payments'),
                            'icon'  => ['fal', 'fa-coins'],
                            'route' => [
                                'name'       => $parent instanceof Shop ? 'grp.org.shops.show.dashboard.payments.accounting.payments.index' : 'grp.org.fulfilments.show.operations.accounting.payments.index',
                                'parameters' => $request->route()->originalParameters()
                            ],
                            'index' => [
                                'number' => $parent->accountingStats->number_payments
                            ]

                        ],
                        [
                            'name'  => __('invoices'),
                            'icon'  => ['fal', 'fa-file-invoice-dollar'],
                            'route' => [
                                'name'       => $parent instanceof Shop ? 'grp.org.shops.show.dashboard.invoices.index' : 'grp.org.fulfilments.show.operations.invoices.paid_invoices.index',
                                'parameters' => $request->route()->originalParameters()
                            ],
                            'index' => [
                                'number' => $parent->orderingStats->number_invoices
                            ]

                        ],

                    ]
                ]

            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.shops.show.dashboard.payments.accounting.dashboard' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.dashboard.payments.accounting.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Accounting')
                        ]
                    ]
                ]
            ),
            'grp.org.fulfilments.show.operations.accounting.dashboard' =>
            array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.operations.accounting.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Accounting')
                        ]
                    ]
                ]
            ),
            default => []
        };
    }


}
