<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:40:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dashboard;

use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\Settings\WithDashboardCurrencyTypeSettings;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithDashboardSettings;
use App\Actions\Traits\WithDashboard;
use App\Enums\Dashboards\OrganisationDashboardSalesTableTabsEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\UI\Organisation\OrgDashboardIntervalTabsEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrganisationDashboard extends OrgAction
{
    use WithDashboard;
    use WithDashboardSettings;
    use WithDashboardIntervalOption;
    use WithDashboardCurrencyTypeSettings;

    public function authorize(ActionRequest $request): bool
    {
        return in_array($this->organisation->id, $request->user()->authorisedOrganisations()->pluck('id')->toArray());
    }

    public function handle(Organisation $organisation, ActionRequest $request): Response
    {
        $userSettings = $request->user()->settings;

        $currentTab = Arr::get($userSettings, 'organisation_dashboard_tab', Arr::first(OrganisationDashboardSalesTableTabsEnum::values()));
        if (!in_array($currentTab, OrganisationDashboardSalesTableTabsEnum::values())) {
            $currentTab = Arr::first(OrganisationDashboardSalesTableTabsEnum::values());
        }

        $saved_interval = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;

        $currency = '_org_currency';

        $tabsBox = [
            [
                'label'         => __('In Basket'),
                'currency_code' => $organisation->currency->code,
                'tabs'          => [
                    [
                        'tab_slug'    => 'in_basket',
                        'label'       => __('In basket'),
                        'value'       => $organisation->orderHandlingStats->number_orders_state_creating,
                        'type'        => 'number',
                        'icon_data'        => [
                            'icon'    => 'fal fa-shopping-basket',
                            'tooltip' => __('In Basket'),
                        ],
                        'information' => [
                            'type'  => 'currency',
                            'label' => $organisation->orderHandlingStats->{"orders_state_creating_amount$currency"},
                        ]
                    ]
                ]
            ],
            [
                'label'         => __('Submitted'),
                'currency_code' => $organisation->currency->code,
                'tabs'          => [
                    [
                        'tab_slug'    => 'submitted_paid',
                        'label'       => __('Submitted Paid'),
                        'value'       => $organisation->orderHandlingStats->number_orders_state_submitted_paid,
                        'type'        => 'number',
                        'icon_data'   => [
                            'tooltip' => __('Submitted Paid'),
                            'icon'    => 'fal fa-check-circle',
                            'class'   => 'text-green-600',
                            'color'   => 'lime',
                            'app'     => [
                                'name' => 'check-circle',
                                'type' => 'font-awesome-5'
                            ]
                        ],
                        'information' => [
                            'label' => $organisation->orderHandlingStats->{"orders_state_submitted_paid_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                    [
                        'tab_slug'    => 'submitted_unpaid',
                        'label'       => __('Submitted Unpaid'),
                        'value'       => $organisation->orderHandlingStats->number_orders_state_submitted_not_paid,
                        'type'        => 'number',
                        'icon_data'   =>
                            [
                                'tooltip' => __('Submitted Unpaid'),
                                'icon'    => 'fal fa-circle',
                                'class'   => 'text-gray-500',
                                'color'   => 'gray',
                                'app'     => [
                                    'name' => 'circle',
                                    'type' => 'font-awesome-5'
                                ]
                            ],
                        'information' => [
                            'label' => $organisation->orderHandlingStats->{"orders_state_submitted_not_paid_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ]
                ]
            ],
            [
                'label'         => __('Warehouse'),
                'currency_code' => $organisation->currency->code,
                'tabs'          => [
                    [
                        'tab_slug'    => 'in_warehouse',
                        'label'       => __('Waiting'),
                        'value'       => $organisation->orderHandlingStats->number_orders_state_in_warehouse,
                        'type'        => 'number',
                        'icon_data'   => [
                            'tooltip' => __('Waiting'),
                            'icon'    => 'fal fa-snooze',
                        ],
                        'information' => [
                            'label' => $organisation->orderHandlingStats->{"orders_state_in_warehouse_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                    [
                        'tab_slug'    => 'handling',
                        'label'       => __('Picking'),
                        'value'       => $organisation->orderHandlingStats->number_orders_state_handling,
                        'type'        => 'number',
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::HANDLING->value],
                        'information' => [
                            'label' => $organisation->orderHandlingStats->{"orders_state_handling_amount$currency"},
                            'type'  => 'currency',
                        ]
                    ],
                    [
                        'tab_slug'    => 'handling_blocked',
                        'label'       => __('Blocked'),
                        'value'       => $organisation->orderHandlingStats->number_orders_state_handling_blocked,
                        'type'        => 'number',
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::HANDLING_BLOCKED->value],
                        'information' => [
                            'label' => $organisation->orderHandlingStats->{"orders_state_handling_blocked_amount$currency"},
                            'type'  => 'currency',
                        ]
                    ],
                    [
                        'tab_slug'    => 'packed',
                        'label'       => __('Packed'),
                        'value'       => $organisation->orderHandlingStats->number_orders_state_packed,
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::PACKED->value],
                        'information' => [
                            'label' => $organisation->orderHandlingStats->{"orders_state_packed_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                ]
            ],
            [
                'label'         => __('Waiting for dispatch'),
                'currency_code' => $organisation->currency->code,
                'tabs'          => [

                    [
                        'tab_slug'    => 'finalised',
                        'label'       => __('Finalised'),
                        'value'       => $organisation->orderHandlingStats->number_orders_state_finalised,
                        'icon_data'   => [
                            'icon'    => 'fal fa-box-check',
                            'tooltip' => __('Finalised'),
                        ],
                        'information' => [
                            'label' => $organisation->orderHandlingStats->{"orders_state_finalised_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                ]
            ],
            [
                'label'         => __('Dispatched Today'),
                'currency_code' => $organisation->currency->code,
                'tabs'          => [
                    [
                        'tab_slug'    => 'dispatched_today',
                        'label'       => __('Dispatched Today'),
                        'value'       => $organisation->orderHandlingStats->number_orders_dispatched_today,
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::DISPATCHED->value],
                        'type'        => 'number',
                        'information' => [
                            'label' => $organisation->orderHandlingStats->{"orders_dispatched_today_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                ]
            ]
        ];

        $dashboard = [
            'super_blocks' => [
                [
                    'id'        => 'organisation_dashboard_tab',
                    'intervals' => [
                        'options'           => $this->dashboardIntervalOption(),
                        'value'             => Arr::get($userSettings, 'selected_interval', 'all'),  // fix this
                        'range_interval'    => DashboardIntervalFilters::run($saved_interval)
                    ],
                    'settings'  => [

                        'model_state_type' => $this->dashboardModelStateTypeSettings($userSettings, 'left'),
                        'data_display_type'    => $this->dashboardDataDisplayTypeSettings($userSettings),
                        'currency_type'   => $this->dashboardCurrencyTypeSettings($organisation, $userSettings),
                    ],
                    'blocks'    => [
                        [
                            'id'          => 'sales_table',
                            'type'        => 'table',
                            'current_tab' => $currentTab,
                            'tabs'        => OrganisationDashboardSalesTableTabsEnum::navigation(),
                            'tables'      => OrganisationDashboardSalesTableTabsEnum::tables($organisation),
                            'charts'      => []
                        ],
                    ],
                    'tabs_box' => [
                        'current'    => $this->tab,
                        'navigation' => $tabsBox
                    ],
                ]

            ]
        ];


        return Inertia::render(
            'Dashboard/OrganisationDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters(), __('Dashboard')),
                'dashboard'   => $dashboard

            ]
        );
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
