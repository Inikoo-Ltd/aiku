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
        $isShop = $this->isShopContext($data);

        $columns = [
            'label' => [
                'formatted_value' => $data['name'] ?? $data['code'] ?? 'Unknown',
                'align'           => 'left',
                'icon'            => $data['slug'] ?? 'unknown',
                'route_target'    => $isShop ? [
                    'name'  => 'grp.org.shops.show.crm.platforms.show',
                    'parameters' => [
                        'organisation' => $data['organisation_slug'],
                        'shop'         => $data['shop_slug'],
                        'platform'     => $data['slug'],
                    ],
                    'key_date_filter' => 'between[date]'
                ] : [
                    'name'  => 'grp.platforms.show',
                    'parameters' => [
                        'platform' => $data['slug'],
                    ],
                    'key_date_filter' => 'between[date]'
                ],
            ],
            'label_minified' => [
                'formatted_value' => $data['slug'] ?? $data['code'] ?? 'Unknown',
                'align'           => 'left',
                'icon'            => $data['slug'] ?? 'unknown',
                'route_target'    => $isShop ? [
                    'name'  => 'grp.org.shops.show.crm.platforms.show',
                    'parameters' => [
                        'organisation' => $data['organisation_slug'],
                        'shop'         => $data['shop_slug'],
                        'platform'     => $data['slug'],
                    ],
                    'key_date_filter' => 'between[date]'
                ] : [
                    'name'  => 'grp.platforms.show',
                    'parameters' => [
                        'platform' => $data['slug'],
                    ],
                    'key_date_filter' => 'between[date]'
                ],
            ]
        ];

        $columns = array_merge(
            $columns,
            $this->getDashboardColumnsFromArray($data, [
                'customer_clients',
                'customer_clients_minified',
                'sales_grp_currency',
                'sales_grp_currency_minified',
                'sales_grp_currency_delta',
                'sales_percentage'
            ])
        );

        if ($isShop) {
            $routeTargets = $this->buildRouteTargets($data);

            $columns = array_merge(
                $columns,
                $this->getDashboardColumnsFromArray($data, [
                    'invoices'           => $routeTargets['invoices'],
                    'invoices_minified'  => $routeTargets['invoices'],
                    'invoices_delta',
                    'channels'           => $routeTargets['channels'],
                    'channels_minified'  => $routeTargets['channels'],
                    'customers'          => $routeTargets['customers'],
                    'customers_minified' => $routeTargets['customers'],
                    'portfolios'         => $routeTargets['portfolios'],
                    'portfolios_minified' => $routeTargets['portfolios'],
                ])
            );
        } else {
            $routeTargets = $this->buildRouteTargetsNonShop($data);

            $columns = array_merge(
                $columns,
                $this->getDashboardColumnsFromArray($data, [
                    'invoices'           => $routeTargets['invoices'],
                    'invoices_minified'  => $routeTargets['invoices'],
                    'invoices_delta',
                    'channels'           => $routeTargets['channels'],
                    'channels_minified'  => $routeTargets['channels'],
                    'customers'          => $routeTargets['customers'],
                    'customers_minified' => $routeTargets['customers'],
                    'portfolios'         => $routeTargets['portfolios'],
                    'portfolios_minified' => $routeTargets['portfolios'],
                ])
            );
        }

        $columns['sales']                      = $columns['sales_grp_currency'];
        $columns['sales_minified']             = $columns['sales_grp_currency_minified'];
        $columns['sales_delta']                = $columns['sales_grp_currency_delta'];
        $columns['sales_org_currency']         = $columns['sales_grp_currency'];
        $columns['sales_org_currency_minified'] = $columns['sales_grp_currency_minified'];
        $columns['sales_org_currency_delta']   = $columns['sales_grp_currency_delta'];

        return [
            'slug'    => $data['slug'] ?? 'unknown',
            'state'   => $data['state'] ?? 'active',
            'columns' => $columns,
            'colour'  => null
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

    private function buildRouteTargetsNonShop(array $data): array
    {
        return [
            'invoices' => [
                'route_target' => [
                    'name'       => 'grp.platforms.show',
                    'parameters' => [
                        'platform' => $data['slug'],
                        'tab'      => PlatformTabsEnum::SHOWCASE->value
                    ],
                    'key_date_filter' => 'between[date]'
                ],
            ],
            'channels' => [
                'route_target' => [
                    'name'       => 'grp.platforms.show',
                    'parameters' => [
                        'platform' => $data['slug'],
                        'tab'      => PlatformTabsEnum::CHANNELS->value
                    ],
                    'key_date_filter' => 'between[created_at]'
                ],
            ],
            'customers' => [
                'route_target' => [
                    'name'       => 'grp.platforms.show',
                    'parameters' => [
                        'platform' => $data['slug'],
                        'tab'      => PlatformTabsEnum::CUSTOMERS->value
                    ],
                    'key_date_filter' => 'between[registered_at]'
                ],
            ],
            'portfolios' => [
                'route_target' => [
                    'name'       => 'grp.platforms.show',
                    'parameters' => [
                        'platform' => $data['slug'],
                        'tab'      => PlatformTabsEnum::PRODUCTS->value
                    ],
                    'key_date_filter' => 'between[created_at]'
                ],
            ],
        ];
    }
}
