<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use App\Actions\Utils\Abbreviate;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardBrandSalesResource extends JsonResource
{
    use WithDashboardIntervalValuesFromArray;

    public function toArray($request): array
    {
        $data = (array) $this->resource;

        $columns = [
            'label' => [
                'formatted_value' => $data['name'] ?? 'Unknown',
                'align'           => 'left',
            ],
            'label_minified' => [
                'formatted_value' => Abbreviate::run($data['name'] ?? 'Unknown'),
                'tooltip'         => $data['name'] ?? 'Unknown',
                'align'           => 'left',
            ],
        ];

        $columns = array_merge(
            $columns,
            $this->getDashboardColumnsFromArray($data, [
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
            'slug'    => $data['slug'] ?? 'unknown',
            'state'   => 'active',
            'columns' => $columns,
        ];
    }
}
