<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 18 May 2023 14:27:38 Central European Summer, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\Settings\WithDashboardCurrencyTypeSettings;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithDashboardSettings;
use App\Actions\Traits\WithDashboard;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\Dashboards\DashboardHeaderPlatformSalesResource;
use App\Http\Resources\Dashboards\DashboardPlatformSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalPlatformSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalShopInvoiceCategoriesSalesResource;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowShop extends OrgAction
{
    use WithIntervalsAggregators;
    use WithDashboard;
    use WithDashboardSettings;
    use WithDashboardIntervalOption;
    use WithDashboardCurrencyTypeSettings;

    public function handle(Shop $shop, ActionRequest $request): Response
    {
        $userSettings = $request->user()->settings;

        $saved_interval = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;

        $currency = '_grp_currency';

        $tabsBox = [
            [
                'label'         => __('In Basket'),
                'currency_code' => $shop->currency->code,
                'tabs'          => [
                    [
                        'tab_slug'    => 'in_basket',
                        'label'       => __('In basket'),
                        'value'       => $shop->orderHandlingStats->number_orders_state_creating,
                        'type'        => 'number',
                        'icon_data'        => [
                            'icon'    => 'fal fa-shopping-basket',
                            'tooltip' => __('In Basket'),
                        ],
                        'information' => [
                            'type'  => 'currency',
                            'label' => $shop->orderHandlingStats->{"orders_state_creating_amount$currency"},
                        ]
                    ]
                ]
            ],
            [
                'label'         => __('Submitted'),
                'currency_code' => $shop->currency->code,
                'tabs'          => [
                    [
                        'tab_slug'    => 'submitted_paid',
                        'label'       => __('Submitted Paid'),
                        'value'       => $shop->orderHandlingStats->number_orders_state_submitted_paid,
                        'type'        => 'number',
                        'icon_data'   => OrderPayStatusEnum::typeIcon()[OrderPayStatusEnum::PAID->value],
                        'information' => [
                            'label' => $shop->orderHandlingStats->{"orders_state_submitted_paid_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                    [
                        'tab_slug'    => 'submitted_unpaid',
                        'label'       => __('Submitted Unpaid'),
                        'value'       => $shop->orderHandlingStats->number_orders_state_submitted_not_paid,
                        'type'        => 'number',
                        'icon_data'   => OrderPayStatusEnum::typeIcon()[OrderPayStatusEnum::UNPAID->value],
                        'information' => [
                            'label' => $shop->orderHandlingStats->{"orders_state_submitted_not_paid_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ]
                ]
            ],
            [
                'label'         => __('Warehouse'),
                'currency_code' => $shop->currency->code,
                'tabs'          => [
                    [
                        'tab_slug'    => 'in_warehouse',
                        'label'       => __('Waiting'),
                        'value'       => $shop->orderHandlingStats->number_orders_state_in_warehouse,
                        'type'        => 'number',
                        'icon_data'   => [
                            'tooltip' => __('Waiting'),
                            'icon'    => 'fal fa-snooze',
                        ],
                        'information' => [
                            'label' => $shop->orderHandlingStats->{"orders_state_in_warehouse_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                    [
                        'tab_slug'    => 'handling',
                        'label'       => __('Picking'),
                        'value'       => $shop->orderHandlingStats->number_orders_state_handling,
                        'type'        => 'number',
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::HANDLING->value],
                        'information' => [
                            'label' => $shop->orderHandlingStats->{"orders_state_handling_amount$currency"},
                            'type'  => 'currency',
                        ]
                    ],
                    [
                        'tab_slug'    => 'handling_blocked',
                        'label'       => __('Blocked'),
                        'value'       => $shop->orderHandlingStats->number_orders_state_handling_blocked,
                        'type'        => 'number',
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::HANDLING_BLOCKED->value],
                        'information' => [
                            'label' => $shop->orderHandlingStats->{"orders_state_handling_blocked_amount$currency"},
                            'type'  => 'currency',
                        ]
                    ],
                    [
                        'tab_slug'    => 'packed',
                        'label'       => __('Packed'),
                        'value'       => $shop->orderHandlingStats->number_orders_state_packed,
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::PACKED->value],
                        'information' => [
                            'label' => $shop->orderHandlingStats->{"orders_state_packed_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                ]
            ],
            [
                'label'         => __('Waiting for dispatch'),
                'currency_code' => $shop->currency->code,
                'tabs'          => [

                    [
                        'tab_slug'    => 'finalised',
                        'label'       => __('Finalised'),
                        'value'       => $shop->orderHandlingStats->number_orders_state_finalised,
                        'icon_data'   => [
                            'icon'    => 'fal fa-box-check',
                            'tooltip' => __('Finalised'),
                        ],
                        'information' => [
                            'label' => $shop->orderHandlingStats->{"orders_state_finalised_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                ]
            ],
            [
                'label'         => __('Dispatched Today'),
                'currency_code' => $shop->currency->code,
                'tabs'          => [
                    [
                        'tab_slug'    => 'dispatched_today',
                        'label'       => __('Dispatched Today'),
                        'value'       => $shop->orderHandlingStats->number_orders_dispatched_today,
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::DISPATCHED->value],
                        'type'        => 'number',
                        'information' => [
                            'label' => $shop->orderHandlingStats->{"orders_dispatched_today_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                ]
            ]
        ];

        $dashboard = [
            'super_blocks' => [
                [
                    'id'        => 'shop_dashboard_tab',
                    'intervals' => [
                        'options'        => $this->dashboardIntervalOption(),
                        'value'          => Arr::get($userSettings, 'selected_interval', 'all'),
                        'range_interval' => DashboardIntervalFilters::run($saved_interval),
                    ],
                    'settings'  => [
                        'model_state_type'  => $this->dashboardModelStateTypeSettings($userSettings, 'left'),
                        'data_display_type' => $this->dashboardDataDisplayTypeSettings($userSettings),
                    ],
                    'shop_blocks' => [
                        'interval_data' => json_decode(DashboardTotalShopInvoiceCategoriesSalesResource::make($shop)->toJson()),
                    ],
                    'tabs_box' => [
                        'current'    => $this->tab,
                        'navigation' => $tabsBox
                    ],
                ],
            ],
        ];

        // Experimental
        if ($shop->type->value === 'dropshipping') {
            $dashboard['super_blocks'][0]['blocks'] = [
                [
                    'id'          => 'sales_table',
                    'type'        => 'table',
                    'current_tab' => 'dropship',
                    'tabs'        => [
                        'dropship' => [
                            'title' => 'Dropship',
                        ],
                    ],
                    'tables'      => [
                        'dropship' => [
                            'header' => json_decode(DashboardHeaderPlatformSalesResource::make($shop)->toJson(), true),
                            'body'   => json_decode(DashboardPlatformSalesResource::collection(
                                $shop->platformSalesIntervals()->get()
                            )->toJson(), true),
                            'totals' => json_decode(DashboardTotalPlatformSalesResource::make(
                                $shop->platformSalesIntervals()->get()
                            )->toJson(), true),
                        ],
                    ],
                ],
            ];
        }

        return Inertia::render('Org/Catalogue/Shop', [
            'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
            'dashboard'   => $dashboard,
        ]);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $shop = Shop::where('slug', $routeParameters['shop'])->first();

        return array_merge(
            ShowOrganisationDashboard::make()->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.shops.index',
                                'parameters' => Arr::only($routeParameters, 'organisation')
                            ],
                            'label' => __('Shops'),
                            'icon'  => 'fal fa-bars'
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.dashboard.show',
                                'parameters' => Arr::only($routeParameters, ['organisation', 'shop']),
                            ],
                            'label' => $shop->code,
                            'icon'  => 'fal fa-bars',
                        ],
                    ],
                    'suffix' => $suffix,
                ],
            ],
        );
    }

    private function getStatsBox(Shop $shop): array
    {
        $cleanIntervals = function (array $stats, string $prefix): array {
            $clean = [];
            foreach ($stats as $key => $value) {
                $cleanKey = str_replace($prefix, '', $key);
                $clean[$cleanKey] = $value;
            }
            return $clean;
        };

        $queryBase = CustomerSalesChannel::query()->where('platform_status', true)->where('shop_id', $shop->id)->selectRaw('COUNT(*) as sum_aggregate');

        $totalPlatformsIntervals = [];

        $totalPlatformsIntervals = $this->getIntervalsData(
            stats: $totalPlatformsIntervals,
            queryBase: $queryBase,
            statField: 'total_platforms_',
            dateField: 'created_at',
        );

        $totalPlatformsIntervals = $cleanIntervals($totalPlatformsIntervals, 'total_platforms_');

        $customerChannels = CustomerSalesChannel::where('platform_status', true)->where('shop_id', $shop->id)->with('platform')->get();

        $metas = [];

        foreach (PlatformTypeEnum::cases() as $platformType) {
            $platformTypeName = $platformType->value;

            $platformChannels = $customerChannels->filter(function ($channel) use ($platformTypeName) {
                return $channel->platform->type->value === $platformTypeName;
            });

            $queryPlatform = CustomerSalesChannel::query()
                ->where('platform_status', true)
                ->where('shop_id', $shop->id)
                ->whereHas('platform', function ($q) use ($platformTypeName) {
                    $q->where('type', $platformTypeName);
                })
                ->selectRaw('COUNT(*) as sum_aggregate');

            $platformIntervals = [];

            $platformIntervals = $this->getIntervalsData(
                stats: $platformIntervals,
                queryBase: $queryPlatform,
                statField: "total_{$platformTypeName}_",
                dateField: 'created_at',
            );

            $platformIntervals = $cleanIntervals($platformIntervals, "total_{$platformTypeName}_");

            $platformData = Platform::where('type', $platformType->value)->first();

            $metas[] = [
                'tooltip'   => __($platformType->labels()[$platformTypeName]),
                'icon'      => [
                    'tooltip' => $platformChannels->count() > 0 ? 'active' : 'inactive',
                    'icon'    => $platformChannels->count() > 0 ? 'fas fa-check-circle' : 'fas fa-times-circle',
                    'class'   => $platformChannels->count() > 0 ? 'text-green-500' : 'text-red-500',
                ],
                'logo_icon' => $platformTypeName,
                'count'     => $platformIntervals,
                'route'     => $platformData ? [
                    'name' => 'grp.org.shops.show.crm.platforms.show',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'shop'         => $this->shop->slug,
                        'platform'     => $platformData->slug,
                    ],
                ] : null,
            ];
        }

        return [
            'label'        => __('Customers Channels'),
            'color'        => '#E87928',
            'icon'         => [
                'icon'          => 'fal fa-code-branch',
                'tooltip'       => __('Channels'),
                'icon_rotation' => '90',
            ],
            'value'        => $totalPlatformsIntervals,
            'metas'        => $metas,
        ];
    }
}
