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
                        'interval_data' => json_decode(
                            DashboardTotalShopInvoiceCategoriesSalesResource::make($shop)->toJson()
                        ),
                         'stats_box' => $shop->type->value === 'dropshipping' ? $this->getStatsBox($shop) : null,
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
