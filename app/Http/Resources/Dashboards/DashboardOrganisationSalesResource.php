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
                        'organisation' => $data['slug'] ?? '',
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'inBasket' => [
                'route_target' => [
                    'name' => 'grp.org.overview.orders_in_basket.index',
                    'parameters' => [
                        'organisation' => $data['slug'] ?? '',
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'registrations' => [
                'route_target' => [
                    'name' => 'grp.org.overview.customers.index',
                    'parameters' => [
                        'organisation' => $data['slug'] ?? '',
                    ],
                    'key_date_filter' => 'between[registered_at]',
                ],
            ],
            'organisations' => [
                'route_target' => [
                    'name' => 'grp.org.dashboard.show',
                    'parameters' => [
                        'organisation' => $data['slug'] ?? '',
                    ],
                ],
            ],
        ];

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $data['name'] ?? '',
                    'align'           => 'left',
                    ...$routeTargets['organisations']
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => $data['code'] ?? '',
                    'tooltip'         => $data['name'] ?? '',
                    'align'           => 'left',
                    ...$routeTargets['organisations']
                ]
            ],
            $this->getDashboardColumnsFromArray($data, [
                'baskets_created_org_currency' => $routeTargets['inBasket'],
                'baskets_created_org_currency_minified' => $routeTargets['inBasket'],
                'baskets_created_grp_currency' => $routeTargets['inBasket'],
                'baskets_created_grp_currency_minified' => $routeTargets['inBasket'],
                'registrations' => $routeTargets['registrations'],
                'registrations_minified' => $routeTargets['registrations'],
                'registrations_delta',
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
            'slug'    => $data['slug'] ?? '',
            'state'   => 'active',
            'columns' => $columns,
            'colour'  => $data['colour'] ?? null,
        ];
    }
}
