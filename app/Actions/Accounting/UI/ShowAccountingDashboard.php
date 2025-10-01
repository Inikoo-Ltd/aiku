<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:26:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithAccountingAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAccountingDashboard extends OrgAction
{
    use WithAccountingAuthorisation;

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);

        return $organisation;
    }


    public function htmlResponse(Organisation $organisation, ActionRequest $request): Response
    {
        $parameters = $request->route()->originalParameters();

        return Inertia::render(
            'Org/Accounting/AccountingDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Accounting'),
                'pageHead'    => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-abacus'],
                        'title' => __('Accounting')
                    ],
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'title' => __('Accounting')
                    ],
                    'title'     => __('Accounting'),

                ],


                'flatTreeMaps' => [
                    [

                        [
                            'name'         => __('Accounts'),
                            'icon'         => ['fal', 'fa-money-check-alt'],
                            'route'        => [
                                'name'       => 'grp.org.accounting.payment-accounts.index',
                                'parameters' => $parameters
                            ],
                            'index'        => [
                                'number' => $organisation->accountingStats->number_payment_accounts
                            ],
                            'rightSubLink' => [
                                'tooltip'    => __('Payment methods'),
                                'icon'       => ['fal', 'fa-cash-register'],
                                'labelStyle' => 'bordered',
                                'route'      => [
                                    'name'       => 'grp.org.accounting.org_payment_service_providers.index',
                                    'parameters' => $parameters
                                ],

                            ]

                        ],
                        [
                            'name'  => __('Payments'),
                            'icon'  => ['fal', 'fa-coins'],
                            'route' => [
                                'name'       => 'grp.org.accounting.payments.index',
                                'parameters' => $parameters
                            ],
                            'index' => [
                                'number' => $organisation->accountingStats->number_payments
                            ]


                        ],


                    ],
                    [
                        [
                            'name'         => __('Invoices categories'),
                            'tooltip'    => __('Invoice categories'),
                            'route'      => [
                                'name'       => 'grp.org.accounting.invoice-categories.index',
                                'parameters' => $parameters
                            ],
                            'index'        => [
                                'number' => $organisation->accountingStats->number_invoice_categories
                            ],

                        ],
                        [
                            'name'         => __('Invoices'),
                            'icon'         => ['fal', 'fa-file-invoice-dollar'],
                            'route'        => [
                                'name'       => 'grp.org.accounting.invoices.index',
                                'parameters' => $parameters
                            ],
                            'index'        => [
                                'number' => $organisation->orderingStats->number_invoices
                            ],
                        ],
                    ]
                ]

            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.accounting.shops.show.dashboard' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.accounting.shops.show.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Accounting'),
                        ]
                    ]
                ]
            ),
            default =>
            array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.accounting.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Accounting'),
                        ]
                    ]
                ]
            )
        };
    }
}
