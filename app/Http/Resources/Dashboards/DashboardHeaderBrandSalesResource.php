<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\SysAdmin\Group;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardHeaderBrandSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;

    public function toArray($request): array
    {
        /** @var Group $model */
        $model = $this->resource;

        $deltaLabel = __('Change versus 1 Year ago');

        return [
            'slug'    => $model->slug,
            'columns' => [
                'label' => [
                    'formatted_value'   => __('Brand'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'align'             => 'left',
                ],
                'label_minified' => [
                    'formatted_value'   => __('Brand'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',
                    'align'             => 'left',
                ],
                'invoices' => [
                    'formatted_value'   => __('Invoices'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'invoices',
                ],
                'invoices_minified' => [
                    'formatted_value'   => __('Invoices'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'invoices',
                ],
                'invoices_delta' => [
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $deltaLabel,
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'invoices',
                ],
                'customers_invoiced' => [
                    'formatted_value'   => __('Customers Invoiced'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'customers_invoiced',
                ],
                'customers_invoiced_minified' => [
                    'formatted_value'   => __('Customers Invoiced'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'customers_invoiced',
                ],
                'customers_invoiced_delta' => [
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $deltaLabel,
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'customers_invoiced',
                ],
                'sales_org_currency_external' => [
                    'formatted_value'   => __('Sales'),
                    'currency_type'     => 'org',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'sales_org_currency_external',
                ],
                'sales_org_currency_external_minified' => [
                    'formatted_value'   => __('Sales'),
                    'currency_type'     => 'org',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'sales_org_currency_external',
                ],
                'sales_org_currency_external_delta' => [
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $deltaLabel,
                    'currency_type'     => 'org',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'sales_org_currency_external',
                ],
                'sales_grp_currency_external' => [
                    'formatted_value'   => __('Sales'),
                    'currency_type'     => 'grp',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'sales_grp_currency_external',
                ],
                'sales_grp_currency_external_minified' => [
                    'formatted_value'   => __('Sales'),
                    'currency_type'     => 'grp',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'sales_grp_currency_external',
                ],
                'sales_grp_currency_external_delta' => [
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $deltaLabel,
                    'currency_type'     => 'grp',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'sales_grp_currency_external',
                ],
            ],
        ];
    }
}
