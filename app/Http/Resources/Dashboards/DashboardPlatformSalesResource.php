<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\UI\CRM\PlatformTabsEnum;
use App\Models\Dropshipping\PlatformSalesIntervals;
use App\Models\Dropshipping\PlatformShopSalesIntervals;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardPlatformSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;

    public function toArray($request): array
    {
        $columns = [
            'label' => [
                'formatted_value' => $this->resource->name,
                'align'           => 'left',
                'icon'            => $this->resource->slug
            ],
            'label_minified' => [
                'formatted_value' => $this->resource->name,
                'align'           => 'left',
                'icon'            => $this->resource->slug
            ],
            'sales_percentage' => $this->getSalesPercentageIntervals()
        ];

        $columns = array_merge(
            $columns,
            $this->getDashboardTableColumn($this, 'new_customer_client'),
            $this->getDashboardTableColumn($this, 'new_customer_client_minified'),
            $this->getDashboardTableColumn($this, 'sales_grp_currency'),
            $this->getDashboardTableColumn($this, 'sales_grp_currency_minified'),
            $this->getDashboardTableColumn($this, 'sales_grp_currency_delta')
        );

        if ($this->isShopContext()) {
            $routeTargets = [
                'invoices' => [
                    'route_target' => [
                        'name' => 'grp.org.shops.show.crm.platforms.show',
                        'parameters' => [
                            'organisation' => $this->resource->organisation_slug,
                            'shop'         => $this->resource->shop_slug,
                            'platform'     => $this->resource->slug,
                            'tab'          => PlatformTabsEnum::SHOWCASE->value
                        ],
                        'key_date_filter' => 'between[date]'
                    ],
                ],
                'new_channels' => [
                    'route_target' => [
                        'name' => 'grp.org.shops.show.crm.platforms.show',
                        'parameters' => [
                            'organisation' => $this->resource->organisation_slug,
                            'shop'         => $this->resource->shop_slug,
                            'platform'     => $this->resource->slug,
                            'tab'          => PlatformTabsEnum::CHANNELS->value
                        ],
                        'key_date_filter' => 'between[created_at]'
                    ],
                ],
                'new_customers' => [
                    'route_target' => [
                        'name' => 'grp.org.shops.show.crm.platforms.show',
                        'parameters' => [
                            'organisation' => $this->resource->organisation_slug,
                            'shop'         => $this->resource->shop_slug,
                            'platform'     => $this->resource->slug,
                            'tab'          => PlatformTabsEnum::CUSTOMERS->value
                        ],
                        'key_date_filter' => 'between[registered_at]'
                    ],
                ],
                'new_portfolios' => [
                    'route_target' => [
                        'name' => 'grp.org.shops.show.crm.platforms.show',
                        'parameters' => [
                            'organisation' => $this->resource->organisation_slug,
                            'shop'         => $this->resource->shop_slug,
                            'platform'     => $this->resource->slug,
                            'tab'          => PlatformTabsEnum::PRODUCTS->value
                        ],
                        'key_date_filter' => 'between[created_at]'
                    ],
                ],
            ];

            $columns = array_merge(
                $columns,
                $this->getDashboardTableColumn($this, 'invoices', $routeTargets['invoices']),
                $this->getDashboardTableColumn($this, 'invoices_minified', $routeTargets['invoices']),
                $this->getDashboardTableColumn($this, 'invoices_delta'),
                $this->getDashboardTableColumn($this, 'new_channels', $routeTargets['new_channels']),
                $this->getDashboardTableColumn($this, 'new_channels_minified', $routeTargets['new_channels']),
                $this->getDashboardTableColumn($this, 'new_customers', $routeTargets['new_customers']),
                $this->getDashboardTableColumn($this, 'new_customers_minified', $routeTargets['new_customers']),
                $this->getDashboardTableColumn($this, 'new_portfolios', $routeTargets['new_portfolios']),
                $this->getDashboardTableColumn($this, 'new_portfolios_minified', $routeTargets['new_portfolios']),
                $this->getDashboardTableColumn($this, 'sales'),
                $this->getDashboardTableColumn($this, 'sales_minified'),
                $this->getDashboardTableColumn($this, 'sales_delta'),
                $this->getDashboardTableColumn($this, 'sales_org_currency'),
                $this->getDashboardTableColumn($this, 'sales_org_currency_minified'),
                $this->getDashboardTableColumn($this, 'sales_org_currency_delta'),
            );
        } else {
            $columns = array_merge(
                $columns,
                $this->getDashboardTableColumn($this, 'invoices'),
                $this->getDashboardTableColumn($this, 'invoices_minified'),
                $this->getDashboardTableColumn($this, 'invoices_delta'),
                $this->getDashboardTableColumn($this, 'new_channels'),
                $this->getDashboardTableColumn($this, 'new_channels_minified'),
                $this->getDashboardTableColumn($this, 'new_customers'),
                $this->getDashboardTableColumn($this, 'new_customers_minified'),
                $this->getDashboardTableColumn($this, 'new_portfolios'),
                $this->getDashboardTableColumn($this, 'new_portfolios_minified')
            );
        }

        return [
            'slug'    => $this->resource->slug,
            'state'   => 'active',
            'columns' => $columns
        ];
    }

    private function getSalesPercentageIntervals(): array
    {
        $isShopContext = $this->isShopContext();

        if ($isShopContext) {
            $models = PlatformShopSalesIntervals::where('shop_id', $this->resource->shop_id)->get();
            $totalPerInterval = $this->sumIntervalValues($models, 'sales');
            $currentPlatformSales = $this->sumIntervalValues([$this->resource], 'sales');
        } else {
            $models = PlatformSalesIntervals::all();
            $totalPerInterval = $this->sumIntervalValues($models, 'sales_grp_currency');
            $currentPlatformSales = $this->sumIntervalValues([$this->resource], 'sales_grp_currency');
        }

        $result = [];

        foreach (DateIntervalEnum::cases() as $interval) {
            $key = $isShopContext
                ? 'sales_' . $interval->value
                : 'sales_grp_currency_' . $interval->value;

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

    private function isShopContext(): bool
    {
        return !empty($this->resource->shop_id);
    }
}
