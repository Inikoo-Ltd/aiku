<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 19 Nov 2025 14:52:52 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalOffersResource extends JsonResource
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
            'customers',
            'orders',
            'invoices',
            'sales',
        ];

        $summedData = $this->sumIntervalValuesFromArrays($models, $fields);

        $summedData = array_merge($firstModel, $summedData);

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => 'All Offer Campaigns',
                    'align'           => 'left',
                ],
                'label_minified' => [
                    'formatted_value' => 'All',
                    'align'           => 'left',
                ],
            ],
            $this->getDashboardColumnsFromArray($summedData, [
                'customers',
                'customers_minified',
                'orders',
                'orders_minified',
                'orders_delta',
                'invoices',
                'invoices_minified',
                'invoices_delta',
                'sales',
                'sales_minified',
                'sales_delta',
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
                'formatted_value' => 'Total',
                'align'           => 'left',
            ],
            'label_minified' => [
                'formatted_value' => 'Total',
                'align'           => 'left',
            ],
        ];
    }
}
