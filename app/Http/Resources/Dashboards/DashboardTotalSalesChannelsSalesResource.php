<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalSalesChannelsSalesResource extends JsonResource
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
            'refunds',
            'invoices',
            'sales_grp_currency',
        ];

        $summedData = $this->sumIntervalValuesFromArrays($models, $fields);

        $summedData = array_merge([
            'group_currency_code' => $firstModel['group_currency_code'] ?? 'GBP',
        ], $summedData);

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => 'All Marketplaces',
                    'align'           => 'left',
                ],
                'label_minified' => [
                    'formatted_value' => 'All',
                    'tooltip'         => 'All Marketplaces',
                    'align'           => 'left',
                ],
            ],
            $this->getDashboardColumnsFromArray($summedData, [
                'refunds',
                'refunds_minified',
                'refunds_delta',

                'invoices',
                'invoices_minified',
                'invoices_delta',

                'sales_grp_currency',
                'sales_grp_currency_minified',
                'sales_grp_currency_delta',
            ])
        );

        return [
            'slug'    => 'totals',
            'columns' => $columns,
        ];
    }

    private function getEmptyColumns(): array
    {
        return [
            'label' => [
                'formatted_value' => 'All Marketplaces',
                'align'           => 'left',
            ],
            'label_minified' => [
                'formatted_value' => 'All',
                'tooltip'         => 'All Marketplaces',
                'align'           => 'left',
            ],
        ];
    }
}
