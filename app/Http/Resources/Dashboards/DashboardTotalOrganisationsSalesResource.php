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

            'registrations_delta',

            'invoices' => $routeTargets['invoices'],
            'invoices_minified' => $routeTargets['invoices'],
            'invoices_delta',

            'sales_grp_currency',
            'sales_grp_currency_minified',
            'sales_grp_currency_delta',
        ];

        $registrationsColumns = $this->getDashboardColumnsFromArray($summedData, [
            'registrations' => $routeTargets['registrations'],
            'registrations_minified' => $routeTargets['registrations'],
        ]);

        $registrationsColumns = $this->addRegistrationsTooltip($registrationsColumns, $summedData);

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
            $this->getDashboardColumnsFromArray($summedData, $columnsConfig),
            $registrationsColumns
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
