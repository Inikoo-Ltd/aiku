<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalBrandSalesResource extends JsonResource
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
            'invoices',
            'customers_invoiced',
            'sales_grp_currency_external',
            'sales_org_currency_external',
        ];

        $summedData = $this->sumIntervalValuesFromArrays($models, $fields);

        $summedData = array_merge([
            'group_currency_code' => $firstModel['group_currency_code'] ?? 'GBP',
        ], $summedData);

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => __('All Brands'),
                    'align'           => 'left',
                ],
                'label_minified' => [
                    'formatted_value' => 'All',
                    'tooltip'         => __('All Brands'),
                    'align'           => 'left',
                ],
            ],
            $this->getDashboardColumnsFromArray($summedData, [
                'invoices',
                'invoices_minified',
                'invoices_delta',
                'customers_invoiced',
                'customers_invoiced_minified',
                'customers_invoiced_delta',
                'sales_org_currency_external',
                'sales_org_currency_external_minified',
                'sales_org_currency_external_delta',
                'sales_grp_currency_external',
                'sales_grp_currency_external_minified',
                'sales_grp_currency_external_delta',
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
                'formatted_value' => __('All Brands'),
                'align'           => 'left',
            ],
            'label_minified' => [
                'formatted_value' => 'All',
                'tooltip'         => __('All Brands'),
                'align'           => 'left',
            ],
        ];
    }
}
