<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

class DashboardTotalTopCustomersSalesResource extends JsonResource
{
    public function toArray($request): array
    {
        $models = is_array($this->resource) ? $this->resource : [];

        if (empty($models)) {
            return [
                'slug'    => 'totals',
                'columns' => $this->emptyColumns(),
            ];
        }

        $first = $models[0] ?? [];

        $totals = [
            'invoices'           => 0,
            'sales'              => 0.0,
            'sales_org_currency' => 0.0,
            'sales_grp_currency' => 0.0,
        ];

        foreach ($models as $row) {
            $totals['invoices']           += (int) ($row['invoices'] ?? 0);
            $totals['sales']              += (float) ($row['sales'] ?? 0);
            $totals['sales_org_currency'] += (float) ($row['sales_org_currency'] ?? 0);
            $totals['sales_grp_currency'] += (float) ($row['sales_grp_currency'] ?? 0);
        }

        return [
            'slug'    => 'totals',
            'columns' => [
                'label' => [
                    'formatted_value' => __('All top customers'),
                    'align'           => 'left',
                ],
                'label_minified' => [
                    'formatted_value' => __('All'),
                    'tooltip'         => __('All top customers'),
                    'align'           => 'left',
                ],
                'reference' => [
                    'formatted_value' => '',
                    'align'           => 'left',
                ],
                'invoices' => [
                    'raw_value'       => $totals['invoices'],
                    'formatted_value' => Number::format($totals['invoices']),
                ],
                'last_invoiced_at' => [
                    'formatted_value' => '',
                ],
                'sales' => [
                    'raw_value'       => $totals['sales'],
                    'formatted_value' => Number::currency($totals['sales'], $first['shop_currency_code'] ?? 'GBP'),
                ],
                'sales_org_currency' => [
                    'raw_value'       => $totals['sales_org_currency'],
                    'formatted_value' => Number::currency($totals['sales_org_currency'], $first['organisation_currency_code'] ?? 'GBP'),
                ],
                'sales_grp_currency' => [
                    'raw_value'       => $totals['sales_grp_currency'],
                    'formatted_value' => Number::currency($totals['sales_grp_currency'], $first['group_currency_code'] ?? 'GBP'),
                ],
            ],
        ];
    }

    private function emptyColumns(): array
    {
        return [
            'label' => [
                'formatted_value' => __('All top customers'),
                'align'           => 'left',
            ],
            'label_minified' => [
                'formatted_value' => __('All'),
                'tooltip'         => __('All top customers'),
                'align'           => 'left',
            ],
        ];
    }
}
