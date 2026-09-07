<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\UI\CRM\PlatformTabsEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardPlatformSalesResource extends JsonResource
{
    use WithDashboardIntervalValuesFromArray;

    public function toArray($request): array
    {
        $data = (array) $this->resource;

        if (!empty($data['is_sales_channel'])) {
            return $this->salesChannelChildRow($data);
        }

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
            ]
        ];

        $columns = array_merge(
            $columns,
            $this->getDashboardColumnsFromArray($data, [
                'customer_clients',
                'customer_clients_minified',
                'sales_grp_currency_external',
                'sales_grp_currency_external_minified',
                'sales_grp_currency_external_delta',
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

        $columns['sales_external']                       = $columns['sales_grp_currency_external'];
        $columns['sales_external_minified']              = $columns['sales_grp_currency_external_minified'];
        $columns['sales_external_delta']                 = $columns['sales_grp_currency_external_delta'];
        $columns['sales_org_currency_external']          = $columns['sales_grp_currency_external'];
        $columns['sales_org_currency_external_minified'] = $columns['sales_grp_currency_external_minified'];
        $columns['sales_org_currency_external_delta']    = $columns['sales_grp_currency_external_delta'];

        return [
            'slug'    => $data['slug'] ?? 'unknown',
            'state'   => $data['state'] ?? 'active',
            'columns' => $columns,
            'colour'  => null
        ];
    }

    /**
     * Child row under the Manual platform, one per sales channel (Web, API, ...). Channel counts,
     * customers and portfolios have no sales-channel dimension, so those cells render blank
     * instead of a misleading zero.
     */
    private function salesChannelChildRow(array $data): array
    {
        $label = [
            'formatted_value' => '↳ '.($data['name'] ?? 'Unknown'),
            'align'           => 'left',
        ];

        $columns = [
            'label'          => $label,
            'label_minified' => $label,
        ];

        $columns = array_merge(
            $columns,
            $this->getDashboardColumnsFromArray($data, [
                'sales_grp_currency_external',
                'sales_grp_currency_external_minified',
                'sales_grp_currency_external_delta',
                'sales_percentage',
                'invoices',
                'invoices_minified',
                'invoices_delta',
            ])
        );

        foreach (['customer_clients', 'customer_clients_minified', 'channels', 'channels_minified', 'customers', 'customers_minified', 'portfolios', 'portfolios_minified'] as $blankColumn) {
            $columns[$blankColumn] = $this->blankIntervalColumn();
        }

        $columns['sales_external']                       = $columns['sales_grp_currency_external'];
        $columns['sales_external_minified']              = $columns['sales_grp_currency_external_minified'];
        $columns['sales_external_delta']                 = $columns['sales_grp_currency_external_delta'];
        $columns['sales_org_currency_external']          = $columns['sales_grp_currency_external'];
        $columns['sales_org_currency_external_minified'] = $columns['sales_grp_currency_external_minified'];
        $columns['sales_org_currency_external_delta']    = $columns['sales_grp_currency_external_delta'];

        return [
            'slug'        => $data['slug'] ?? 'sales-channel',
            'parent_slug' => $data['parent_slug'] ?? null,
            'state'       => 'active',
            'columns'     => $columns,
            'colour'      => null
        ];
    }

    private function blankIntervalColumn(): array
    {
        return collect(DateIntervalEnum::cases())->mapWithKeys(fn ($interval) => [
            $interval->value => [
                'formatted_value' => '',
                'raw_value'       => 0,
                'tooltip'         => '',
            ]
        ])->toArray();
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
