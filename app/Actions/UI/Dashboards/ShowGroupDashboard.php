<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Dec 2024 00:41:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dashboards;

use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\Settings\WithDashboardCurrencyTypeSettings;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithDashboardSettings;
use App\Actions\Traits\WithDashboard;
use App\Enums\Dashboards\GroupDashboardSalesTableTabsEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowGroupDashboard extends OrgAction
{
    use WithDashboard;
    use WithDashboardSettings;
    use WithDashboardIntervalOption;
    use WithDashboardCurrencyTypeSettings;

    public function handle(Group $group, ActionRequest $request): Response
    {

        $userSettings = $request->user()->settings;

        $currentTab = Arr::get($userSettings, 'group_dashboard_tab', Arr::first(GroupDashboardSalesTableTabsEnum::values()));
        if (!in_array($currentTab, GroupDashboardSalesTableTabsEnum::values())) {
            $currentTab = Arr::first(GroupDashboardSalesTableTabsEnum::values());
        }

        $saved_interval = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;

        $currency = '_grp_currency';

        $tabsBox = [
            [
                'label'         => __('In Basket'),
                'currency_code' => $group->currency->code,
                'tabs'          => [
                    [
                        'tab_slug'    => 'in_basket',
                        'label'       => __('In basket'),
                        'value'       => $group->orderHandlingStats->number_orders_state_creating,
                        'type'        => 'number',
                        'icon_data'        => [
                            'icon'    => 'fal fa-shopping-basket',
                            'tooltip' => __('In Basket'),
                        ],
                        'information' => [
                            'type'  => 'currency',
                            'label' => $group->orderHandlingStats->{"orders_state_creating_amount$currency"},
                        ]
                    ]
                ]
            ],
            [
                'label'         => __('Submitted'),
                'currency_code' => $group->currency->code,
                'tabs'          => [
                    [
                        'tab_slug'    => 'submitted_paid',
                        'label'       => __('Submitted Paid'),
                        'value'       => $group->orderHandlingStats->number_orders_state_submitted_paid,
                        'type'        => 'number',
                        'icon_data'   => OrderPayStatusEnum::typeIcon()[OrderPayStatusEnum::PAID->value],
                        'information' => [
                            'label' => $group->orderHandlingStats->{"orders_state_submitted_paid_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                    [
                        'tab_slug'    => 'submitted_unpaid',
                        'label'       => __('Submitted Unpaid'),
                        'value'       => $group->orderHandlingStats->number_orders_state_submitted_not_paid,
                        'type'        => 'number',
                        'icon_data'   => OrderPayStatusEnum::typeIcon()[OrderPayStatusEnum::UNPAID->value],
                        'information' => [
                            'label' => $group->orderHandlingStats->{"orders_state_submitted_not_paid_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ]
                ]
            ],
            [
                'label'         => __('Warehouse'),
                'currency_code' => $group->currency->code,
                'tabs'          => [
                    [
                        'tab_slug'    => 'in_warehouse',
                        'label'       => __('Waiting'),
                        'value'       => $group->orderHandlingStats->number_orders_state_in_warehouse,
                        'type'        => 'number',
                        'icon_data'   => [
                            'tooltip' => __('Waiting'),
                            'icon'    => 'fal fa-snooze',
                        ],
                        'information' => [
                            'label' => $group->orderHandlingStats->{"orders_state_in_warehouse_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                    [
                        'tab_slug'    => 'handling',
                        'label'       => __('Picking'),
                        'value'       => $group->orderHandlingStats->number_orders_state_handling,
                        'type'        => 'number',
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::HANDLING->value],
                        'information' => [
                            'label' => $group->orderHandlingStats->{"orders_state_handling_amount$currency"},
                            'type'  => 'currency',
                        ]
                    ],
                    [
                        'tab_slug'    => 'handling_blocked',
                        'label'       => __('Blocked'),
                        'value'       => $group->orderHandlingStats->number_orders_state_handling_blocked,
                        'type'        => 'number',
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::HANDLING_BLOCKED->value],
                        'information' => [
                            'label' => $group->orderHandlingStats->{"orders_state_handling_blocked_amount$currency"},
                            'type'  => 'currency',
                        ]
                    ],
                    [
                        'tab_slug'    => 'packed',
                        'label'       => __('Packed'),
                        'value'       => $group->orderHandlingStats->number_orders_state_packed,
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::PACKED->value],
                        'information' => [
                            'label' => $group->orderHandlingStats->{"orders_state_packed_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                ]
            ],
            [
                'label'         => __('Waiting for dispatch'),
                'currency_code' => $group->currency->code,
                'tabs'          => [

                    [
                        'tab_slug'    => 'finalised',
                        'label'       => __('Finalised'),
                        'value'       => $group->orderHandlingStats->number_orders_state_finalised,
                        'icon_data'   => [
                            'icon'    => 'fal fa-box-check',
                            'tooltip' => __('Finalised'),
                        ],
                        'information' => [
                            'label' => $group->orderHandlingStats->{"orders_state_finalised_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                ]
            ],
            [
                'label'         => __('Dispatched Today'),
                'currency_code' => $group->currency->code,
                'tabs'          => [
                    [
                        'tab_slug'    => 'dispatched_today',
                        'label'       => __('Dispatched Today'),
                        'value'       => $group->orderHandlingStats->number_orders_dispatched_today,
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::DISPATCHED->value],
                        'type'        => 'number',
                        'information' => [
                            'label' => $group->orderHandlingStats->{"orders_dispatched_today_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                ]
            ]
        ];

        $dashboard = [
            'super_blocks' => [
                [
                    'id'        => 'group_dashboard_tab',
                    'intervals' => [
                        'options' => $this->dashboardIntervalOption(),
                        'value'   => Arr::get($userSettings, 'selected_interval', 'all'),  // fix this
                        'range_interval'    => DashboardIntervalFilters::run($saved_interval)
                    ],
                    'settings'  => [
                        'model_state_type' => $this->dashboardModelStateTypeSettings($userSettings, 'left'),
                        'data_display_type'    => $this->dashboardDataDisplayTypeSettings($userSettings),
                        'currency_type'   => $this->dashboardCurrencyTypeSettings($group, $userSettings),
                    ],
                    'blocks'    => [
                        [
                            'id'          => 'sales_table',
                            'type'        => 'table',
                            'current_tab' => $currentTab,
                            'tabs'        => GroupDashboardSalesTableTabsEnum::navigation(),
                            'tables'      => GroupDashboardSalesTableTabsEnum::tables($group),
                            'charts'      => [] // <-- to do (refactor), need to call OrganisationDashboardSalesChartsEnum

                        ]
                    ],
                    'tabs_box' => [
                        'current'    => $this->tab,
                        'navigation' => $tabsBox
                    ],
                ]

            ]
        ];


        return Inertia::render(
            'Dashboard/GrpDashboard',
            [
                'title'       => __('Dashboard Group'),
                'breadcrumbs' => $this->getBreadcrumbs(__('Dashboard')),
                'dashboard'   => $dashboard
            ]
        );
    }

    public function asController(ActionRequest $request): Response
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($group, $request);
    }

    public function getBreadcrumbs($label = null): array
    {
        return [
            [

                'type'   => 'simple',
                'simple' => [
                    'icon'  => 'fal fa-tachometer-alt-fast',
                    'label' => $label,
                    'route' => [
                        'name' => 'grp.dashboard.show'
                    ]
                ]

            ],

        ];
    }
}
