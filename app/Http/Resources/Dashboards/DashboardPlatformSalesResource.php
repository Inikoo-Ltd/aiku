<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Enums\UI\CRM\PlatformTabsEnum;
use App\Models\Dropshipping\PlatformShopSalesIntervals;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardPlatformSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;

    // Note: Experimental Data (Need to be checked)
    public function toArray($request): array
    {
        if ($this->shouldHidePlatform()) {
            return [];
        }

        $routeTargets = [
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.crm.platforms.show',
                    'parameters' => [
                        'organisation' => $this->resource->shop->organisation->slug,
                        'shop'         => $this->resource->shop->slug,
                        'platform'     => $this->resource->platform->slug,
                        'tab'          => PlatformTabsEnum::SHOWCASE->value
                    ],
                ],
            ],
            'new_channels' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.crm.platforms.show',
                    'parameters' => [
                        'organisation' => $this->resource->shop->organisation->slug,
                        'shop'         => $this->resource->shop->slug,
                        'platform'     => $this->resource->platform->slug,
                        'tab'          => PlatformTabsEnum::CHANNELS->value
                    ],
                ],
            ],
            'new_customers' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.crm.platforms.show',
                    'parameters' => [
                        'organisation' => $this->resource->shop->organisation->slug,
                        'shop'         => $this->resource->shop->slug,
                        'platform'     => $this->resource->platform->slug,
                        'tab'          => PlatformTabsEnum::CUSTOMERS->value
                    ],
                ],
            ],
            'new_portfolios' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.crm.platforms.show',
                    'parameters' => [
                        'organisation' => $this->resource->shop->organisation->slug,
                        'shop'         => $this->resource->shop->slug,
                        'platform'     => $this->resource->platform->slug,
                        'tab'          => PlatformTabsEnum::PRODUCTS->value
                    ],
                ],
            ],
        ];

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $this->resource->platform->name ?? '',
                    'align'           => 'left',
                    'icon'            => $this->resource->platform->slug ?? '',
                ]
            ],
            $this->getDashboardTableColumn($this, 'invoices', $routeTargets['invoices']),
            $this->getDashboardTableColumn($this, 'new_channels', $routeTargets['new_channels']),
            $this->getDashboardTableColumn($this, 'new_customers', $routeTargets['new_customers']),
            $this->getDashboardTableColumn($this, 'new_portfolios', $routeTargets['new_portfolios']),
            $this->getDashboardTableColumn($this, 'new_customer_client'),
        );

        if ($this->resource instanceof PlatformShopSalesIntervals) {
            $columns = array_merge($columns, $this->getDashboardTableColumn($this, 'sales'));

            $models = PlatformShopSalesIntervals::where('shop_id', $this->resource->shop_id)->get();
            $totalSales = collect($this->sumIntervalValues($models, 'sales'))->sum();

            $sales = collect($this->sumIntervalValues([$this->resource], 'sales'))->sum();

            $columns['sales_percentage'] = $this->getSalesPercentageIntervals(
                $this->resource->shop_id,
                $this->resource
            );
        }

        return [
            'slug'      => $this->resource->platform->slug ?? '',
            'state'     => 'active',
            'columns'   => $columns,
            'colour'    => ''
        ];
    }

    private function shouldHidePlatform(): bool
    {
        $keyMetrics = ['invoices', 'sales', 'new_customers', 'new_channels'];

        foreach ($keyMetrics as $metric) {
            $value = $this->resource->{$metric . '_all'} ?? 0;

            if ($value > 0) {
                return false;
            }

            $currentYearValue = $this->resource->{$metric . '_1y'} ?? 0;
            if ($currentYearValue > 0) {
                return false;
            }
        }

        return true;
    }
    private function getSalesPercentageIntervals($shopId, $currentModel): array
    {
        $models = PlatformShopSalesIntervals::where('shop_id', $shopId)->get();

        $totalPerInterval = $this->sumIntervalValues($models, 'sales');

        $currentPlatformSales = $this->sumIntervalValues([$currentModel], 'sales');

        $result = [];

        foreach (\App\Enums\DateIntervals\DateIntervalEnum::cases() as $interval) {
            $key = 'sales_' . $interval->value;

            $total = $totalPerInterval[$key] ?? 0;
            $value = $currentPlatformSales[$key] ?? 0;

            $percentage = $total > 0 ? ($value / $total) * 100 : 0;

            $result[$interval->value] = [
                'raw_value'       => $percentage,
                'formatted_value' => number_format($percentage, 2) . '%',
                'align'           => 'right',
            ];
        }

        return $result;
    }
}
