<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use App\Actions\Utils\Abbreviate;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardShopSalesResource extends JsonResource
{
    use WithDashboardIntervalValuesFromArray;

    public function toArray($request): array
    {
        $data = (array) $this->resource;

        $routeTargets = [
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.dashboard.invoices.index',
                    'parameters' => [
                        'organisation' => $data['organisation_slug'] ?? 'unknown',
                        'shop' => $data['slug'] ?? 'unknown',
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'registrations' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.crm.customers.index',
                    'parameters' => [
                        'organisation' => $data['organisation_slug'] ?? 'unknown',
                        'shop' => $data['slug'] ?? 'unknown',
                    ],
                    'key_date_filter' => 'between[registered_at]',
                ],
            ],
            'inBasket' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.ordering.backlog',
                    'parameters' => [
                        'organisation' => $data['organisation_slug'] ?? 'unknown',
                        'shop' => $data['slug'] ?? 'unknown',
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'shops' => [
                'route_target' => isset($data['id']) ? [
                    'name' => 'grp.helpers.redirect_shops_from_dashboard',
                    'parameters' => [
                        'shop' => $data['id'],
                    ],
                ] : null,
            ],
        ];

        $columns = [
            'label' => [
                'formatted_value' => $data['name'] ?? 'Unknown',
                'align'           => 'left',
                ...$routeTargets['shops']
            ],
            'label_minified' => [
                'formatted_value' => Abbreviate::run($data['name'] ?? 'Unknown'),
                'tooltip'         => $data['name'] ?? 'Unknown',
                'align'           => 'left',
                ...$routeTargets['shops']
            ]
        ];

        $registrationsColumns = $this->getDashboardColumnsFromArray($data, [
            'registrations' => $routeTargets['registrations'],
            'registrations_minified' => $routeTargets['registrations'],
        ]);

        $registrationsColumns = $this->addRegistrationsTooltip($registrationsColumns, $data);

        $columns = array_merge(
            $columns,
            $this->getDashboardColumnsFromArray($data, [
                'baskets_created' => $routeTargets['inBasket'],
                'baskets_created_minified' => $routeTargets['inBasket'],
                'baskets_created_org_currency' => $routeTargets['inBasket'],
                'baskets_created_org_currency_minified' => $routeTargets['inBasket'],
                'baskets_created_grp_currency' => $routeTargets['inBasket'],
                'baskets_created_grp_currency_minified' => $routeTargets['inBasket'],
                'invoices' => $routeTargets['invoices'],
                'invoices_minified' => $routeTargets['invoices'],
                'invoices_delta',
            ]),
            $registrationsColumns,
            $this->getDashboardColumnsFromArray($data, [
                'registrations_delta',
                'sales',
                'sales_minified',
                'sales_delta',
                'sales_org_currency',
                'sales_org_currency_minified',
                'sales_org_currency_delta',
                'sales_grp_currency',
                'sales_grp_currency_minified',
                'sales_grp_currency_delta',
            ])
        );

        return [
            'slug'    => $data['slug'] ?? 'unknown',
            'state'   => ($data['state'] ?? ShopStateEnum::OPEN->value) == ShopStateEnum::OPEN->value ? 'active' : 'inactive',
            'columns' => $columns,
            'colour'  => $data['colour'] ?? '',
        ];
    }

    private function addRegistrationsTooltip(array $columns, array $data): array
    {
        $intervals = ['tdy', 'ld', '3d', '1w', '1m', '1q', '1y', 'all', 'ytd', 'qtd', 'mtd', 'wtd', 'lm', 'lw', 'ctm'];

        foreach (['registrations', 'registrations_minified'] as $columnKey) {
            if (isset($columns[$columnKey])) {
                foreach ($intervals as $interval) {
                    if (isset($columns[$columnKey][$interval])) {
                        $withOrders = $data["registrations_with_orders_{$interval}"] ?? 0;
                        $withoutOrders = $data["registrations_without_orders_{$interval}"] ?? 0;

                        $columns[$columnKey][$interval]['tooltip'] = sprintf(
                            'With orders: %s | Without orders: %s',
                            number_format($withOrders),
                            number_format($withoutOrders)
                        );
                    }
                }
            }
        }

        return $columns;
    }
}
