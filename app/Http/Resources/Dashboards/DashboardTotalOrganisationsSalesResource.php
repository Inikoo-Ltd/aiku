<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Mar 2025 15:20:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalOrganisationsSalesResource extends JsonResource
{
    use WithDashboardIntervalValuesFromArray;

    public function toArray($request): array
    {
        $models = $this->resource;

        if (empty($models)) {
            return [
                'slug'    => 'totals',
                'columns' => $this->getEmptyColumns(),
            ];
        }

        $firstModel = is_array($models) ? ($models[0] ?? []) : [];

        $fields = [
            'baskets_created_grp_currency',
            'invoices',
            'registrations',
            'registrations_with_orders',
            'registrations_without_orders',
            'sales_grp_currency',
        ];

        $summedData = $this->sumIntervalValuesFromArrays($models, $fields);

        $summedData = array_merge([
            'group_currency_code' => $firstModel['group_currency_code'] ?? 'GBP',
        ], $summedData);

        $routeTargets = [
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.overview.accounting.invoices.index',
                    'parameters' => [],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'registrations' => [
                'route_target' => [
                    'name' => 'grp.overview.crm.customers.index',
                    'parameters' => [],
                    'key_date_filter' => 'between[registered_at]',
                ],
            ],
            'inBasket' => [
                'route_target' => [
                    'name' => 'grp.overview.ordering.orders_in_basket.index',
                    'parameters' => [],
                    'key_date_filter' => 'between[date]',
                ],
            ],
        ];

        $columnsConfig = [
            'baskets_created_grp_currency' => $routeTargets['inBasket'],
            'baskets_created_grp_currency_minified' => $routeTargets['inBasket'],

            'registrations_with_orders',
            'registrations_without_orders',

            'registrations' => $routeTargets['registrations'],
            'registrations_minified' => $routeTargets['registrations'],
            'registrations_delta',

            'invoices' => $routeTargets['invoices'],
            'invoices_minified' => $routeTargets['invoices'],
            'invoices_delta',

            'sales_grp_currency',
            'sales_grp_currency_minified',
            'sales_grp_currency_delta',
        ];

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => 'All Organisations',
                    'align'           => 'left',
                ],
                'label_minified' => [
                    'formatted_value' => 'All',
                    'tooltip'         => 'All Organisations',
                    'align'           => 'left',
                ],
            ],
            $this->getDashboardColumnsFromArray($summedData, $columnsConfig)
        );

        $columns['baskets_created_org_currency'] = $columns['baskets_created_grp_currency'];
        $columns['baskets_created_org_currency_minified'] = $columns['baskets_created_grp_currency_minified'];
        $columns['sales_org_currency'] = $columns['sales_grp_currency'];
        $columns['sales_org_currency_minified'] = $columns['sales_grp_currency_minified'];
        $columns['sales_org_currency_delta'] = $columns['sales_grp_currency_delta'];

        return [
            'slug'    => 'totals',
            'columns' => $columns,
        ];
    }

    private function getEmptyColumns(): array
    {
        return [
            'label' => [
                'formatted_value' => 'All Organisations',
                'align'           => 'left',
            ],
            'label_minified' => [
                'formatted_value' => 'All',
                'tooltip'         => 'All Organisations',
                'align'           => 'left',
            ],
        ];
    }
}
