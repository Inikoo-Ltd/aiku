<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalPlatformSalesResource extends JsonResource
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
            'channels',
            'customers',
            'portfolios',
            'customer_clients',
            // 'sales',
            // 'sales_org_currency',
            'sales_grp_currency',
        ];

        $summedData = $this->sumIntervalValuesFromArrays($models, $fields);

        $summedData = array_merge([
            'group_currency_code' => $firstModel['group_currency_code'] ?? 'GBP',
        ], $summedData);

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => 'All Platforms',
                    'align'           => 'left',
                ],
                'label_minified' => [
                    'formatted_value' => 'All Platforms',
                    'align'           => 'left',
                ],
                'sales_percentage' => [
                    'formatted_value' => '100%',
                ]
            ],
            $this->getDashboardColumnsFromArray($summedData, [
                'customer_clients',
                'customer_clients_minified',

                'invoices',
                'invoices_minified',
                'invoices_delta',

                'channels',
                'channels_minified',

                'customers',
                'customers_minified',

                'portfolios',
                'portfolios_minified',

                // 'sales',
                // 'sales_minified',
                // 'sales_delta',

                // 'sales_org_currency',
                // 'sales_org_currency_minified',
                // 'sales_org_currency_delta',

                'sales_grp_currency',
                'sales_grp_currency_minified',
                'sales_grp_currency_delta',
            ])
        );

        $columns['sales'] = $columns['sales_grp_currency'];
        $columns['sales_minified'] = $columns['sales_grp_currency_minified'];
        $columns['sales_delta'] = $columns['sales_grp_currency_delta'];
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
                'formatted_value' => 'All Platforms',
                'align'           => 'left',
            ],
            'label_minified' => [
                'formatted_value' => 'All Platforms',
                'align'           => 'left',
            ],
        ];
    }
}
