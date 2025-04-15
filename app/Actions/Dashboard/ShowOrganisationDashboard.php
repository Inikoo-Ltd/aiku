<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:40:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dashboard;

use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithDashboardSettings;
use App\Actions\Traits\WithDashboard;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dashboards\OrganisationDashboardSalesTableTabsEnum;
use App\Enums\UI\Organisation\OrgDashboardIntervalTabsEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrganisationDashboard extends OrgAction
{
    use WithDashboard;

    // <-- to delete

    use WithDashboardSettings;
    use WithDashboardIntervalOption;

    public function authorize(ActionRequest $request): bool
    {
        return in_array($this->organisation->id, $request->user()->authorisedOrganisations()->pluck('id')->toArray());
    }

    public function handle(Organisation $organisation, ActionRequest $request): Response
    {
        $userSettings = $request->user()->settings; // <-- to delete
        $settings     = Arr::get($request->user()->settings, 'ui.state.organisation_dashboard', []); // <-- to delete


        $dashboard = [
            'super_blocks' => [
                [
                    'id'        => 'main_sales',
                    'intervals' => [
                        'options' => $this->dashboardIntervalOption(),
                        'value'   => Arr::get($userSettings, 'selected_interval', 'all')  // fix this
                    ],
                    'settings'  => [

                        'model_state'       => $this->dashboardModelStateSettings($userSettings, 'left'),
                        'data_display_type' => $this->dashboardDataDisplayTypeSettings($userSettings),
                    ],


                    'blocks'    => [
                        [
                            'id'          => 'sales_table',
                            'type'        => 'table',
                            'current_tab' => Arr::get($settings, 'sales_table_tab', Arr::first(OrganisationDashboardSalesTableTabsEnum::values())),
                            'tabs'        => OrganisationDashboardSalesTableTabsEnum::navigation(),
                            'tables'      => OrganisationDashboardSalesTableTabsEnum::tables($organisation),
                            'charts'      => [] // <-- to do (refactor) need to call OrganisationDashboardSalesChartsEnum

                        ],
                        [
                            'id'          => 'sales_chart',
                            'type'        => 'pie-chart',
                            'data_source' => 'sales'
                        ],
                        [
                            'id'          => 'invoices_chart',
                            'type'        => 'pie-chart',
                            'data_source' => 'invoices'
                        ],
//                        [
//                            'id'          => 'average_invoice_amount_chart',
//                            'type'        => 'bar-chart',
//                            'data_source' => 'average_invoice_amount'
//                        ]
                    ]

                ]

            ]
        ];


        return Inertia::render(
            'Dashboard/OrganisationDashboard',
            [
                'breadcrumbs'     => $this->getBreadcrumbs($request->route()->originalParameters(), __('Dashboard')),
                'dashboard_stats' => $this->getDashboardInterval($organisation, $userSettings), // <-- to delete
                'dashboard'       => $dashboard // <-- new dashboard

            ]
        );
    }

    // to delete
    public function getDashboardInterval(Organisation $organisation, array $userSettings): array
    {
        $selectedInterval  = Arr::get($userSettings, 'selected_interval', 'all');
        $selectedAmount    = Arr::get($userSettings, 'selected_amount', true);
        $selectedShopState = Arr::get($userSettings, 'selected_shop_state', 'open');
        $shops             = $organisation->shops->where('state', $selectedShopState);
        $dashboard         = [
            'interval_options' => $this->getIntervalOptions(),
            'settings'         => [
                'db_settings'         => $userSettings,
                'key_currency'        => 'org',
                'key_shop'            => 'open',
                'selected_amount'     => $selectedAmount,
                'selected_shop_state' => $selectedShopState,
                'options_shop'        => [
                    [
                        'value' => 'open',
                        'label' => __('Open')
                    ],
                    [
                        'value' => 'closed',
                        'label' => __('Closed')
                    ]
                ],
                'options_currency'    => [
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
            'currency_code'    => $organisation->currency->code,
            'current'          => $this->tabDashboardInterval,
            'table'            => [
                [
                    'tab_label' => __('Invoice per store'),
                    'tab_slug'  => 'invoices',
                    'tab_icon'  => 'fal fa-file-invoice-dollar',
                    'type'      => 'table',
                    'data'      => null
                ],
                [
                    'tab_label' => __('Invoices categories'),
                    'tab_slug'  => 'invoice_categories',
                    'tab_icon'  => 'fal fa-sitemap',
                    'type'      => 'table',
                    'data'      => null
                ]
            ],
            'widgets'          => [
                'column_count' => 3,
                'components'   => []
            ]
        ];

        $selectedCurrency = Arr::get($userSettings, 'selected_currency_in_org', 'org');

        if ($this->tabDashboardInterval == OrgDashboardIntervalTabsEnum::INVOICES->value) {
            $dashboard['table'][0]['data'] = $this->getInvoices($organisation, $shops, $selectedInterval, $dashboard, $selectedCurrency);
            $shopCurrencies                = [];
            foreach ($shops as $shop) {
                $shopCurrencies[] = $shop->currency->symbol;
            }
            $shopCurrenciesSymbol = implode('/', array_unique($shopCurrencies));
        } elseif ($this->tabDashboardInterval == OrgDashboardIntervalTabsEnum::INVOICE_CATEGORIES->value) {
            $selectedInvoiceCategoryState                             = Arr::get($userSettings, 'selected_invoice_category_state', 'open');
            $dashboard['settings']['selected_invoice_category_state'] = $selectedInvoiceCategoryState;
            if ($selectedInvoiceCategoryState == 'open') {
                $invoiceCategories = $organisation->invoiceCategories->whereIn('state', [InvoiceCategoryStateEnum::ACTIVE, InvoiceCategoryStateEnum::COOLDOWN]);
            } else {
                $invoiceCategories = $organisation->invoiceCategories->where('state', InvoiceCategoryStateEnum::CLOSED->value);
            }
            $dashboard['table'][1]['data'] = $this->getInvoiceCategories($organisation, $invoiceCategories, $selectedInterval, $dashboard, $selectedCurrency);

            $invoiceCategoryCurrencies = [];
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

        $orderingHandling                   = $organisation->orderHandlingStats;
        $currencyCode                       = $organisation->currency->code;
        $orderingHandling = $organisation->orderHandlingStats;
        $currencyCode = $organisation->currency->code;
        $crmStats = $organisation->crmStats;
        $dashboard['widgets']['components'] = array_merge(
            $dashboard['widgets']['components'],
            [
                $this->getWidget(
                    data: [
                        'value'       => $crmStats->number_customers,
                        'description' => __('Total Customer'),
                        'type'        => 'number',
                        /*  'route'       => [
                             'name'       => 'grp.org.fulfilments.show.operations.pallet-deliveries.index',
                             'parameters' => [
                                 'organisation' => $fulfilment->organisation->slug,
                                 'fulfilment'   => $fulfilment->slug,
                                 'deliveries_elements[state]' => 'submitted'
                             ]
                         ] */
                    ],
                ),
                $this->getWidget(
                    data: [
                        'value'       => $crmStats->number_customers_state_active,
                        'description' => __('Active Customer'),
                        'type'        => 'number',
                        /*  'route'       => [
                             'name'       => 'grp.org.fulfilments.show.operations.pallet-deliveries.index',
                             'parameters' => [
                                 'organisation' => $fulfilment->organisation->slug,
                                 'fulfilment'   => $fulfilment->slug,
                                 'deliveries_elements[state]' => 'submitted'
                             ]
                         ] */
                    ],
                ),
                $this->getWidget(
                    data: [
                        'value'       => $crmStats->number_customers_trade_state_many,
                        'description' => __('Customers With Orders'),
                        'type'        => 'number',
                         /* 'route'       => [
                             'name'       => 'grp.org.fulfilments.show.operations.pallet-deliveries.index',
                             'parameters' => [
                                 'organisation' => $fulfilment->organisation->slug,
                                 'fulfilment'   => $fulfilment->slug,
                                 'deliveries_elements[state]' => 'submitted'
                             ]
                         ] */
                    ],
                ),
                !app()->environment('production') ?
                $this->getWidget(
                    data: [
                        'label'         => __('In Basket'),
                        'currency_code' => $currencyCode,
                        'type'          => 'number_amount',
                        'tabs'          => [
                            [
                                'label'       => $orderingHandling->number_orders_state_creating,
                                'type'        => 'number',
                                'icon'        => 'fal fa-tachometer-alt',
                                'label' => $orderingHandling->number_orders_state_creating,
                                'type' => 'number',
                                'icon' => 'fal fa-tachometer-alt',
                                'information' => [
                                    'label' => $orderingHandling->{"orders_state_creating_amount_org_currency"},
                                    'type'  => 'currency'
                                ]
                            ],

                            [
                                'label' => $orderingHandling->orders_state_creating_amount_org_currency,
                                'type' => 'number',
                                'icon' => 'fal fa-tachometer-alt',
                                'information' => [
                                    'label' => $orderingHandling->{"orders_state_creating_amount_org_currency"},
                                    'type' => 'currency'
                                ]
                            ]
                        ]

                    ]
                ) : [],
                $this->getWidget(
                    data: [
                        'label'         => __('Submitted'),
                        'currency_code' => $currencyCode,
                        'type'          => 'number_amount',
                        'tabs'          => [
                            [
                                'label'       => $orderingHandling->number_orders_state_submitted_paid,
                                'type'        => 'number',
                                'icon'        => 'fal fa-tachometer-alt',
                                'information' => [
                                    'label' => $orderingHandling->{"orders_state_submitted_paid_amount_org_currency"},
                                    'type'  => 'currency'
                                ]
                            ],
                            [
                                'label'       => $orderingHandling->number_orders_state_submitted_not_paid,
                                'type'        => 'number',
                                'icon'        => 'fal fa-tachometer-alt',
                                'information' => [
                                    'label' => $orderingHandling->{"orders_state_submitted_not_paid_amount_org_currency"},
                                    'type'  => 'currency'
                                ]
                            ]
                        ]
                    ]
                ),
                $this->getWidget(
                    data: [
                        'label'         => __('Picking'),
                        'currency_code' => $currencyCode,
                        'type'          => 'number_amount',
                        'tabs'          => [
                            [
                                'label'       => $orderingHandling->number_orders_state_handling,
                                'type'        => 'number',
                                'icon'        => 'fal fa-tachometer-alt',
                                'information' => [
                                    'label' => $orderingHandling->{"orders_state_handling_amount_org_currency"},
                                    'type'  => 'currency'
                                ]
                            ],
                            [
                                'label'       => $orderingHandling->number_orders_state_handling_blocked,
                                'type'        => 'number',
                                'icon'        => 'fal fa-tachometer-alt',
                                'information' => [
                                    'label' => $orderingHandling->{"orders_state_handling_blocked_amount_org_currency"},
                                    'type'  => 'currency'
                                ]
                            ]
                        ]
                    ]
                ),
                $this->getWidget(
                    data: [
                        'label'         => __('Invoicing'),
                        'currency_code' => $currencyCode,
                        'type'          => 'number_amount',
                        'tabs'          => [
                            [
                                'label'       => $orderingHandling->number_orders_state_packed,
                                'icon'        => 'fal fa-box',
                                'iconClass'   => 'text-teal-500',
                                'information' => [
                                    'label' => $orderingHandling->{"orders_state_packed_amount_org_currency"},
                                    'type'  => 'currency'
                                ]
                            ],
                            [
                                'label'       => $orderingHandling->number_orders_state_finalised,
                                'icon'        => 'fal fa-box-check',
                                'iconClass'   => 'text-orange-500',
                                'information' => [
                                    'label' => $orderingHandling->{"orders_state_finalised_amount_org_currency"},
                                    'type'  => 'currency'
                                ]
                            ]
                        ]
                    ]
                ),
                $this->getWidget(
                    data: [
                        'label'         => __('Today'),
                        'currency_code' => $currencyCode,
                        'type'          => 'number_amount',
                        'tabs'          => [
                            [
                                'label'       => $orderingHandling->number_orders_dispatched_today,
                                'type'        => 'number',
                                'information' => [
                                    'label' => $orderingHandling->{"orders_dispatched_today_amount_org_currency"},
                                    'type'  => 'currency'
                                ]
                            ]
                        ]
                    ]
                )
            ]
        );

        return $dashboard;
    }

    // to delete
    public function getInvoices(Organisation $organisation, $shops, $selectedInterval, &$dashboard, $selectedCurrency): array
    {
        $visualData = [];

        $data = [];

        $dashboard = $this->setDashboardTableData(
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
                        'name'       => 'grp.org.shops.show.dashboard.show',
                        'parameters' => [
                            'organisation' => $organisation->slug,
                            'shop'         => $child->slug
                        ]
                    ],
                    'route_invoice' => [
                        'name'       => 'grp.org.shops.show.dashboard.invoices.index',
                        'parameters' => [
                            'organisation'  => $organisation->slug,
                            'shop'          => $child->slug,
                            'between[date]' => $this->getDateIntervalFilter($selectedInterval)
                        ]
                    ],
                    'route_refund'  => [
                        'name'       => 'grp.org.accounting.refunds.index',
                        'parameters' => [
                            'organisation'  => $organisation->slug,
                            'between[date]' => $this->getDateIntervalFilter($selectedInterval)
                        ]
                    ],
                ];

                if ($child->type == ShopTypeEnum::FULFILMENT) {
                    $routes['route']         = [
                        'name'       => 'grp.org.fulfilments.show.operations.dashboard',
                        'parameters' => [
                            'organisation' => $organisation->slug,
                            'fulfilment'   => $child->slug
                        ]
                    ];
                    $routes['route_invoice'] = [
                        'name'       => 'grp.org.fulfilments.show.operations.invoices.all.index',
                        'parameters' => [
                            'organisation'  => $organisation->slug,
                            'fulfilment'    => $child->slug,
                            'between[date]' => $this->getDateIntervalFilter($selectedInterval)
                        ]
                    ];
                }

                return $routes;
            }
        );

        if (!Arr::get($visualData, 'sales_data')) {
            return $data;
        }

        // visual pie sales
        $dashboard = $this->setVisualInvoiceSales($organisation, $visualData, $dashboard);

        // visual pie invoices
        $dashboard = $this->setVisualInvoices($visualData, $dashboard);

        // visual pie refunds
        $dashboard = $this->setVisualAvgInvoices($organisation, $visualData, $dashboard);


        return $data;
    }

    // to delete
    public function getInvoiceCategories(Organisation $organisation, $invoiceCategories, $selectedInterval, &$dashboard, $selectedCurrency): array
    {
        $visualData = [];
        $data       = [];


        $dashboard = $this->setDashboardTableData(
            $organisation,
            $invoiceCategories,
            $dashboard,
            $visualData,
            $data,
            $selectedCurrency,
            $selectedInterval,
            fn ($child) => [
                'route'         => [
                    'name'       => 'grp.org.accounting.invoice-categories.show',
                    'parameters' => [
                        'organisation'    => $organisation->slug,
                        'invoiceCategory' => $child->slug
                    ]
                ],
                'route_invoice' => [
                    'name'       => 'grp.org.accounting.invoice-categories.show.invoices.index',
                    'parameters' => [
                        'organisation'    => $organisation->slug,
                        'invoiceCategory' => $child->slug,
                        'between[date]'   => $this->getDateIntervalFilter($selectedInterval)
                    ]
                ],
                'route_refund'  => [
                    'name'       => 'grp.org.accounting.invoice-categories.show.invoices.index',
                    'parameters' => [
                        'organisation'    => $organisation->slug,
                        'invoiceCategory' => $child->slug,
                        'between[date]'   => $this->getDateIntervalFilter($selectedInterval),
                        'tab'             => 'refunds'
                    ]
                ],
            ],
        );

        if (!Arr::get($visualData, 'sales_data')) {
            return $data;
        }

        // visual pie sales
        $dashboard = $this->setVisualInvoiceSales($organisation, $visualData, $dashboard);

        // visual pie invoices
        $dashboard = $this->setVisualInvoices($visualData, $dashboard);

        // visual pie refunds
        $dashboard = $this->setVisualAvgInvoices($organisation, $visualData, $dashboard);


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
