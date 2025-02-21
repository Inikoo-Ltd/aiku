<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:40:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\WithDashboard;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\UI\Organisation\OrgDashboardIntervalTabsEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowOrganisationDashboard extends OrgAction
{
    use AsAction;
    use WithDashboard;

    public function authorize(ActionRequest $request): bool
    {
        return in_array($this->organisation->id, $request->user()->authorisedOrganisations()->pluck('id')->toArray());
    }

    public function handle(Organisation $organisation, ActionRequest $request): Response
    {
        $userSettings = $request->user()->settings;

        return Inertia::render(
            'Dashboard/OrganisationDashboard',
            [
                'breadcrumbs'     => $this->getBreadcrumbs($request->route()->originalParameters(), __('Dashboard')),
                'dashboard_stats' => $this->getDashboardInterval($organisation, $userSettings),
            ]
        );
    }

    public function getDashboardInterval(Organisation $organisation, array $userSettings): array
    {
        $selectedInterval = Arr::get($userSettings, 'selected_interval', 'all');
        $selectedAmount   = Arr::get($userSettings, 'selected_amount', true);
        $selectedShopState = Arr::get($userSettings, 'selected_shop_state', 'open');
        $shops            = $organisation->shops->where('state', $selectedShopState);
        $dashboard = [
            'interval_options' => $this->getIntervalOptions(),
            'settings'         => [
                'db_settings'          => $userSettings,
                'key_currency'         => 'org',
                'key_shop'             => 'open',
                'selected_amount'      => $selectedAmount,
                'selected_shop_state'  => $selectedShopState,
                'options_shop'         => [
                    [
                        'value' => 'open',
                        'label' => __('Open')
                    ],
                    [
                        'value' => 'closed',
                        'label' => __('Closed')
                    ]
                ],
                'options_currency'     => [
                    [
                        'value' => 'org',
                        'label' => $organisation->currency->symbol,
                    ],
                    [
                        'value' => 'shop',
                        'label' => '',
                    ]
                ]
            ],
            'currency_code' => $organisation->currency->code,
            'current' => $this->tabDashboardInterval,
            'table' => [
                [
                    'tab_label' => __('Invoice per store'),
                    'tab_slug'  => 'invoices',
                    'tab_icon'  => 'fal fa-file-invoice-dollar',
                    'type'     => 'table',
                    'data' => null
                ],
                [
                    'tab_label' => __('Invoices categories'),
                    'tab_slug'  => 'invoice_categories',
                    'tab_icon'  => 'fal fa-sitemap',
                    'type'     => 'table',
                    'data' => null
                ]
            ],
            'widgets'          => [
                'column_count' => 5,
                'components'   => []
            ]
        ];

        $selectedCurrency = Arr::get($userSettings, 'selected_currency_in_org', 'org');

        if ($this->tabDashboardInterval == OrgDashboardIntervalTabsEnum::INVOICES->value) {
            $dashboard['table'][0]['data'] = $this->getInvoices($organisation, $shops, $selectedInterval, $dashboard, $selectedCurrency);
            $shopCurrencies   = [];
            foreach ($shops as $shop) {
                $shopCurrencies[] = $shop->currency->symbol;
            }
            $shopCurrenciesSymbol = implode('/', array_unique($shopCurrencies));
        } elseif ($this->tabDashboardInterval == OrgDashboardIntervalTabsEnum::INVOICE_CATEGORIES->value) {
            $invoiceCategories = $organisation->invoiceCategories;
            $dashboard['table'][1]['data'] = $this->getInvoiceCategories($organisation, $invoiceCategories, $selectedInterval, $dashboard, $selectedCurrency);

            $invoiceCategoryCurrencies   = [];
            foreach ($invoiceCategories as $invoiceCategory) {
                $invoiceCategoryCurrencies[] = $invoiceCategory->currency->symbol;
            }
            $shopCurrenciesSymbol = implode('/', array_unique($invoiceCategoryCurrencies));
        }

        $dashboard['settings']['options_currency'][1]['label'] = $shopCurrenciesSymbol;

        if ($selectedCurrency == 'shop') {
            if ($organisation->currency->symbol != $shopCurrenciesSymbol) {
                data_forget($dashboard, 'currency_code');
            }
        }

        return $dashboard;
    }

    public function getInvoices(Organisation $organisation, $shops, $selectedInterval, &$dashboard, $selectedCurrency): array
    {
        $visualData = [];

        $data = [];

        $this->setDashboardTableData(
            $organisation,
            $shops,
            $dashboard,
            $visualData,
            $data,
            $selectedCurrency,
            $selectedInterval,
            function ($child) use ($selectedInterval, $organisation) {
                $routes = [
                            'route'         => [
                    'name'       => 'grp.org.shops.show.dashboard',
                    'parameters' => [
                        'organisation' => $organisation->slug,
                        'shop'         => $child->slug
                    ]
                ],
                'route_invoice' => [
                    'name'       => 'grp.org.shops.show.ordering.invoices.index',
                    'parameters' => [
                        'organisation' => $organisation->slug,
                        'shop' => $child->slug,
                        'between[date]' => $this->getDateIntervalFilter($selectedInterval)
                    ]
                ],
                'route_refund' => [
                    'name'       => 'grp.org.shops.show.ordering.refunds.index',
                    'parameters' => [
                        'organisation' => $organisation->slug,
                        'shop'        => $child->slug,
                        'between[date]' => $this->getDateIntervalFilter($selectedInterval)
                    ]
                ],
                ];

                if ($child->type == ShopTypeEnum::FULFILMENT) {
                    $routes['route'] = [
                        'name'       => 'grp.org.fulfilments.show.operations.dashboard',
                        'parameters' => [
                            'organisation' => $organisation->slug,
                            'fulfilment'   => $child->slug
                        ]
                    ];
                    $routes['route_invoice'] = [
                        'name'       => 'grp.org.fulfilments.show.operations.invoices.all.index',
                        'parameters' => [
                            'organisation' => $organisation->slug,
                            'fulfilment'   => $child->slug,
                            'between[date]' => $this->getDateIntervalFilter($selectedInterval)
                        ]
                    ];
                    $routes['route_refund'] = [
                        'name'       => 'grp.org.fulfilments.show.operations.invoices.refunds.index',
                        'parameters' => [
                            'organisation' => $organisation->slug,
                            'fulfilment'   => $child->slug,
                            'between[date]' => $this->getDateIntervalFilter($selectedInterval)
                        ]
                    ];
                }
                return $routes;
            }
        );

        $total = $dashboard['total'];

        if (!Arr::get($visualData, 'sales_data')) {
            return $data;
        }

        if (array_filter(Arr::get($visualData, 'sales_data.datasets.0.data'), fn ($value) => $value !== '0.00')) {
            $combined = array_map(null, $visualData['sales_data']['labels'], $visualData['sales_data']['currency_codes'], $visualData['sales_data']['datasets'][0]['data']);

            usort($combined, function ($a, $b) {
                return floatval($b[2]) <=> floatval($a[2]);
            });

            $visualData['sales_data']['labels']              = array_column($combined, 0);
            $visualData['sales_data']['currency_codes']      = array_column($combined, 1);
            $visualData['sales_data']['datasets'][0]['data'] = array_column($combined, 2);

            $dashboard['widgets']['components'][] = $this->getWidget(
                type: 'chart_display',
                data: [
                    'status'        => $total['total_sales'] < 0 ? 'danger' : '',
                    'value'         => $total['total_sales'],
                    'currency_code' => $organisation->currency->code,
                    'type'          => 'currency',
                    'description'   => __('Total sales')
                ],
                visual: [
                    'type'  => 'doughnut',
                    'value' => [
                        'labels'         => $visualData['sales_data']['labels'],
                        'currency_codes' => $visualData['sales_data']['currency_codes'],
                        'datasets'       => $visualData['sales_data']['datasets']
                    ],
                ]
            );
        }


        if (array_filter(Arr::get($visualData, 'invoices_data.datasets.0.data'))) {
            $combinedInvoices = array_map(null, $visualData['invoices_data']['labels'], $visualData['invoices_data']['currency_codes'], $visualData['invoices_data']['datasets'][0]['data']);

            usort($combinedInvoices, function ($a, $b) {
                return floatval($b[2]) <=> floatval($a[2]);
            });

            $visualData['invoices_data']['labels']              = array_column($combinedInvoices, 0);
            $visualData['invoices_data']['currency_codes']      = array_column($combinedInvoices, 1);
            $visualData['invoices_data']['datasets'][0]['data'] = array_column($combinedInvoices, 2);

            $dashboard['widgets']['components'][] = $this->getWidget(
                type: 'chart_display',
                data: [
                    'value'       => $total['total_invoices'],
                    'type'        => 'number',
                    'description' => __('Total invoices')
                ],
                visual: [
                    'type'  => 'doughnut',
                    'value' => [
                        'labels'         => Arr::get($visualData, 'invoices_data.labels'),
                        'datasets'       => Arr::get($visualData, 'invoices_data.datasets'),
                    ],
                ]
            );


            $amountMap = [];
            $totalMap = [];

            foreach ($combined as $entry) {
                $amountMap[$entry[0]] = [
                    'currency_code' => $entry[1],
                    'amount' => (float) $entry[2]
                ];
            }

            foreach ($combinedInvoices as $entry) {
                $totalMap[$entry[0]] = (int) $entry[2];
            }

            $averages = [];

            $totalAvg = 0;
            foreach ($amountMap as $label => $amount) {
                if (isset($totalMap[$label]) && $totalMap[$label] > 0) {
                    $averages[$label] = $amount['amount'] / $totalMap[$label];
                } else {
                    $averages[$label] = 0;
                }
                $totalAvg += $averages[$label];
            }

            if ($totalAvg == 0) {
                return $data;
            }

            $dashboard['widgets']['components'][] = $this->getWidget(
                type: 'chart_display',
                data: [
                    'description' => __('Average amount value')
                ],
                visual: [
                    'type'  => 'bar',
                    'value' => [
                        'labels'         => array_keys($amountMap),
                        'currency_codes' => Arr::pluck($amountMap, 'currency_code'),
                        'datasets'       => [
                            [
                                'data' => Arr::flatten($averages),
                            ]
                        ]
                    ],
                ]
            );
        }




        return $data;
    }

    public function getInvoiceCategories(Organisation $organisation, $invoiceCategories, $selectedInterval, &$dashboard, $selectedCurrency): array
    {
        $visualData = [];
        $data = [];

        $this->setDashboardTableData(
            $organisation,
            $invoiceCategories,
            $dashboard,
            $visualData,
            $data,
            $selectedCurrency,
            $selectedInterval,
            fn () => [],
        );


        return $data;
    }

    public function asController(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request)->withTabDashboardInterval(OrgDashboardIntervalTabsEnum::values());

        return $this->handle($organisation, $request);
    }

    public function getBreadcrumbs(array $routeParameters, $label = null): array
    {
        return [
            [

                'type'   => 'simple',
                'simple' => [
                    'icon'  => 'fal fa-tachometer-alt-fast',
                    'label' => $label,
                    'route' => [
                        'name'       => 'grp.org.dashboard.show',
                        'parameters' => $routeParameters
                    ]
                ]

            ],

        ];
    }
}
