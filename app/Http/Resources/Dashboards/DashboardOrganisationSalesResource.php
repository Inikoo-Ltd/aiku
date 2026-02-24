<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Mar 2025 14:51:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardOrganisationSalesResource extends JsonResource
{
    use WithDashboardIntervalValuesFromArray;

    public function toArray($request): array
    {
        $data = is_array($this->resource) ? $this->resource : $this->resource->toArray();

        $routeTargets = [
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.org.accounting.invoices.index',
                    'parameters' => [
                        'organisation' => $data['slug'] ?? 'unknown',
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'inBasket' => [
                'route_target' => [
                    'name' => 'grp.org.overview.orders_in_basket.index',
                    'parameters' => [
                        'organisation' => $data['slug'] ?? 'unknown',
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'registrations' => [
                'route_target' => [
                    'name' => 'grp.org.overview.customers.index',
                    'parameters' => [
                        'organisation' => $data['slug'] ?? 'unknown',
                    ],
                    'key_date_filter' => 'between[registered_at]',
                ],
            ],
            'organisations' => [
                'route_target' => [
                    'name' => 'grp.org.dashboard.show',
                    'parameters' => [
                        'organisation' => $data['slug'] ?? 'unknown',
                    ],
                ],
            ],
        ];

        $registrationsColumns = $this->getDashboardColumnsFromArray($data, [
            'registrations' => $routeTargets['registrations'],
            'registrations_minified' => $routeTargets['registrations'],
        ]);

        $registrationsColumns = $this->addRegistrationsTooltip($registrationsColumns, $data);

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $data['name'] ?? 'Unknown',
                    'align'           => 'left',
                    ...$routeTargets['organisations']
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => $data['code'] ?? 'Unknown',
                    'tooltip'         => $data['name'] ?? 'Unknown',
                    'align'           => 'left',
                    ...$routeTargets['organisations']
                ]
            ],
            $this->getDashboardColumnsFromArray($data, [
                'baskets_created_org_currency' => $routeTargets['inBasket'],
                'baskets_created_org_currency_minified' => $routeTargets['inBasket'],
                'baskets_created_grp_currency' => $routeTargets['inBasket'],
                'baskets_created_grp_currency_minified' => $routeTargets['inBasket'],
            ]),
            $registrationsColumns,
            $this->getDashboardColumnsFromArray($data, [
                'registrations_delta',
                'registrations_with_orders',
                'registrations_without_orders',
                'invoices' => $routeTargets['invoices'],
                'invoices_minified' => $routeTargets['invoices'],
                'invoices_delta',
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
            'state'   => 'active',
            'columns' => $columns,
            'colour'  => $data['colour'] ?? null,
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
