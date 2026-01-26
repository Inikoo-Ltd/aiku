<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use App\Enums\UI\CRM\PlatformTabsEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardPlatformSalesResource extends JsonResource
{
    use WithDashboardIntervalValuesFromArray;

    public function toArray($request): array
    {
        $data = (array) $this->resource;

        $columns = [
            'label' => [
                'formatted_value' => $data['name'] ?? $data['code'] ?? 'Unknown',
                'align'           => 'left',
                'icon'            => $data['slug'] ?? $data['code'] ?? 'Unknown',
            ],
            'label_minified' => [
                'formatted_value' => $data['slug'] ?? $data['code'] ?? 'Unknown',
                'align'           => 'left',
                'icon'            => $data['slug'] ?? $data['code'] ?? 'Unknown',
            ]
        ];

        // Common columns for all contexts
        $columns = array_merge(
            $columns,
            $this->getDashboardColumnsFromArray($data, [
                'customer_clients',
                'customer_clients_minified',
                'sales_grp_currency',
                'sales_grp_currency_minified',
                'sales_grp_currency_delta',
                'sales_percentage' // Now available from GetPlatformTimeSeriesStats
            ])
        );

        if ($this->isShopContext($data)) {
            // Build route targets
            $routeTargets = $this->buildRouteTargets($data);

            // Add shop-specific columns with route targets
            $columns = array_merge(
                $columns,
                $this->getDashboardColumnsFromArray($data, [
                    'invoices' => $routeTargets['invoices'],
                    'invoices_minified' => $routeTargets['invoices'],
                    'invoices_delta',
                    'channels' => $routeTargets['channels'],
                    'channels_minified' => $routeTargets['channels'],
                    'customers' => $routeTargets['customers'],
                    'customers_minified' => $routeTargets['customers'],
                    'portfolios' => $routeTargets['portfolios'],
                    'portfolios_minified' => $routeTargets['portfolios'],
                    'sales',
                    'sales_minified',
                    'sales_delta',
                    'sales_org_currency',
                    'sales_org_currency_minified',
                    'sales_org_currency_delta',
                ])
            );
        } else {
            // Add non-shop columns (without route targets)
            $columns = array_merge(
                $columns,
                $this->getDashboardColumnsFromArray($data, [
                    'invoices',
                    'invoices_minified',
                    'invoices_delta',
                    'channels',
                    'channels_minified',
                    'customers',
                    'customers_minified',
                    'portfolios',
                    'portfolios_minified',
                    'sales',
                    'sales_minified',
                    'sales_delta',
                    'sales_org_currency',
                    'sales_org_currency_minified',
                    'sales_org_currency_delta',
                ])
            );
        }

        return [
            'slug'    => $data['slug'] ?? 'unknown',
            'state'   => $data['state'] ?? 'active',
            'columns' => $columns,
            'colour'  => ''
        ];
    }

    private function isShopContext(array $data): bool
    {
        return !empty($data['shop_id']);
    }

    private function buildRouteTargets(array $data): array
    {
        return [
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.crm.platforms.show',
                    'parameters' => [
                        'organisation' => $data['organisation_slug'],
                        'shop'         => $data['shop_slug'],
                        'platform'     => $data['slug'],
                        'tab'          => PlatformTabsEnum::SHOWCASE->value
                    ],
                    'key_date_filter' => 'between[date]'
                ],
            ],
            'channels' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.crm.platforms.show',
                    'parameters' => [
                        'organisation' => $data['organisation_slug'],
                        'shop'         => $data['shop_slug'],
                        'platform'     => $data['slug'],
                        'tab'          => PlatformTabsEnum::CHANNELS->value
                    ],
                    'key_date_filter' => 'between[created_at]'
                ],
            ],
            'customers' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.crm.platforms.show',
                    'parameters' => [
                        'organisation' => $data['organisation_slug'],
                        'shop'         => $data['shop_slug'],
                        'platform'     => $data['slug'],
                        'tab'          => PlatformTabsEnum::CUSTOMERS->value
                    ],
                    'key_date_filter' => 'between[registered_at]'
                ],
            ],
            'portfolios' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.crm.platforms.show',
                    'parameters' => [
                        'organisation' => $data['organisation_slug'],
                        'shop'         => $data['shop_slug'],
                        'platform'     => $data['slug'],
                        'tab'          => PlatformTabsEnum::PRODUCTS->value
                    ],
                    'key_date_filter' => 'between[created_at]'
                ],
            ],
        ];
    }
}
