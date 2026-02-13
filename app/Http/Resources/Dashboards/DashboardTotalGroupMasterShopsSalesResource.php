<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Mar 2025 13:08:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalGroupMasterShopsSalesResource extends JsonResource
{
    use WithDashboardIntervalValuesFromArray;

    public function toArray($request): array
    {
        $masterShops = $this->resource;

        if (empty($masterShops)) {
            return [
                'slug'    => 'totals',
                'columns' => $this->getEmptyColumns(),
            ];
        }

        $firstMasterShop = is_array($masterShops) ? ($masterShops[0] ?? []) : [];

        $fields = [
            'baskets_created_grp_currency',
            'registrations',
            'registrations_with_orders',
            'registrations_without_orders',
            'sales_grp_currency',
            'invoices',
        ];

        $summedData = $this->sumIntervalValuesFromArrays($masterShops, $fields);

        $summedData = array_merge([
            'group_currency_code' => $firstMasterShop['group_currency_code'] ?? 'GBP',
            'group_slug' => $firstMasterShop['group_slug'] ?? 'unknown',
        ], $summedData);

        $routeTargets = [
            'group' => [
                'route_target' => [
                    'name'       => 'grp.dashboard.show',
                    'parameters' => [],
                ],
            ],
        ];

        $registrationsColumns = array_merge(
            $this->getDashboardTableColumnFromArray($summedData, 'registrations'),
            $this->getDashboardTableColumnFromArray($summedData, 'registrations_minified')
        );

        $registrationsColumns = $this->addRegistrationsTooltip($registrationsColumns, $summedData);

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => 'All Master Shops',
                    'align'           => 'left',
                    ...$routeTargets['group']
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => 'All',
                    'tooltip'         => 'All Master Shops',
                    'align'           => 'left',
                    ...$routeTargets['group']
                ]
            ],
            $this->getDashboardTableColumnFromArray($summedData, 'baskets_created_grp_currency'),
            $this->getDashboardTableColumnFromArray($summedData, 'baskets_created_grp_currency_minified'),
            $registrationsColumns,
            $this->getDashboardTableColumnFromArray($summedData, 'registrations_delta'),
            $this->getDashboardTableColumnFromArray($summedData, 'registrations_with_orders'),
            $this->getDashboardTableColumnFromArray($summedData, 'registrations_with_orders_delta'),
            $this->getDashboardTableColumnFromArray($summedData, 'registrations_without_orders'),
            $this->getDashboardTableColumnFromArray($summedData, 'registrations_without_orders_delta'),
            $this->getDashboardTableColumnFromArray($summedData, 'invoices'),
            $this->getDashboardTableColumnFromArray($summedData, 'invoices_minified'),
            $this->getDashboardTableColumnFromArray($summedData, 'invoices_delta'),
            $this->getDashboardTableColumnFromArray($summedData, 'sales_grp_currency'),
            $this->getDashboardTableColumnFromArray($summedData, 'sales_grp_currency_minified'),
            $this->getDashboardTableColumnFromArray($summedData, 'sales_grp_currency_delta')
        );

        return [
            'slug'    => $summedData['group_slug'] ?? 'totals',
            'columns' => $columns
        ];
    }

    private function getEmptyColumns(): array
    {
        return [
            'label' => [
                'formatted_value' => 'All Master Shops',
                'align'           => 'left',
            ],
            'label_minified' => [
                'formatted_value' => 'All',
                'tooltip'         => 'All Master Shops',
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
