<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Aug 2025 16:58:12 Central Standard Time, Mexico-Tokio
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardMasterShopSalesInGroupResource extends JsonResource
{
    use WithDashboardIntervalValuesFromArray;

    public function toArray($request): array
    {
        $data = (array) $this->resource;

        $routeTargets = [
            'master_shop' => [
                'route_target' => [
                    'name' => 'grp.masters.master_shops.show',
                    'parameters' => [
                        'masterShop' => $data['slug'] ?? '',
                    ]
                ]
            ]
        ];

        $columns = [
            'label' => [
                'formatted_value' => $data['name'] ?? 'Unknown',
                'align'           => 'left',
                ...$routeTargets['master_shop']
            ],
            'label_minified' => [
                'formatted_value' => $data['code'] ?? '',
                'tooltip'         => $data['name'] ?? 'Unknown',
                'align'           => 'left',
                'route_target'    => []
            ]
        ];

        $registrationsColumns = $this->getDashboardColumnsFromArray($data, [
            'registrations',
            'registrations_minified',
        ]);

        $registrationsColumns = $this->addRegistrationsTooltip($registrationsColumns, $data);

        $columns = array_merge(
            $columns,
            $this->getDashboardColumnsFromArray($data, [
                'baskets_created_grp_currency',
                'baskets_created_grp_currency_minified',
                'invoices',
                'invoices_minified',
                'invoices_delta',
            ]),
            $registrationsColumns,
            $this->getDashboardColumnsFromArray($data, [
                'registrations_delta',
                'sales_grp_currency',
                'sales_grp_currency_minified',
                'sales_grp_currency_delta',
            ])
        );

        return [
            'slug'    => $data['slug'] ?? '',
            'state'   => ($data['status'] ?? false) ? 'active' : 'inactive',
            'columns' => $columns,
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
