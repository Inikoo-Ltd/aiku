<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Mar 2025 00:55:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalInvoiceCategoriesSalesResource extends JsonResource
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
        $parentType = $firstModel['parent_type'] ?? 'Organisation';

        $fields = [
            'refunds',
            'invoices',
            'sales',
            'sales_org_currency',
            'sales_grp_currency',
        ];

        $summedData = $this->sumIntervalValuesFromArrays($models, $fields);

        $summedData = array_merge($firstModel, $summedData);

        $routeTargets = [
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.org.accounting.invoices.index',
                    'parameters' => [
                        'organisation' => $summedData['organisation_slug'] ?? 'unknown',
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'refunds' => [
                'route_target' => [
                    'name' => 'grp.org.accounting.refunds.index',
                    'parameters' => [
                        'organisation' => $summedData['organisation_slug'] ?? 'unknown',
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
        ];

        $columnsConfig = [];

        if ($parentType === 'Organisation') {
            $columnsConfig = [
                'refunds' => $routeTargets['refunds'],
                'refunds_minified' => $routeTargets['refunds'],
                'refunds_inverse_delta',

                'invoices' => $routeTargets['invoices'],
                'invoices_minified' => $routeTargets['invoices'],
                'invoices_delta',

                'sales_org_currency',
                'sales_org_currency_minified',
                'sales_org_currency_delta',
            ];
        } else {
            $columnsConfig = [
                'refunds' => $routeTargets['refunds'],
                'refunds_minified' => $routeTargets['refunds'],
                'refunds_inverse_delta',

                'invoices' => $routeTargets['invoices'],
                'invoices_minified' => $routeTargets['invoices'],
                'invoices_delta',

                'sales_grp_currency',
                'sales_grp_currency_minified',
                'sales_grp_currency_delta',
            ];
        }

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => 'All Invoice Categories',
                    'align'           => 'left',
                ],
                'label_minified' => [
                    'formatted_value' => 'All',
                    'tooltip'         => 'All Invoice Categories',
                    'align'           => 'left',
                ],
            ],
            $this->getDashboardColumnsFromArray($summedData, $columnsConfig)
        );

        if ($parentType === 'Organisation') {
            $columns['sales'] = $columns['sales_org_currency'];
            $columns['sales_minified'] = $columns['sales_org_currency_minified'];
            $columns['sales_delta'] = $columns['sales_org_currency_delta'];
        } else {
            $columns['sales_org_currency'] = $columns['sales_grp_currency'];
            $columns['sales_org_currency_minified'] = $columns['sales_grp_currency_minified'];
            $columns['sales_org_currency_delta'] = $columns['sales_grp_currency_delta'];
        }

        return [
            'slug'    => 'totals',
            'columns' => $columns,
        ];
    }

    private function getEmptyColumns(): array
    {
        return [
            'label' => [
                'formatted_value' => 'All Invoice Categories',
                'align'           => 'left',
            ],
            'label_minified' => [
                'formatted_value' => 'All',
                'tooltip'         => 'All Invoice Categories',
                'align'           => 'left',
            ],
        ];
    }
}
