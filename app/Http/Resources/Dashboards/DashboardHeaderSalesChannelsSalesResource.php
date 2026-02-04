<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\SysAdmin\Group;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardHeaderSalesChannelsSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;

    public function toArray($request): array
    {
        /** @var Group $group */
        $group = $this;

        $deltaLabel = __('Change versus 1 Year ago');

        $salesChannelColumns = [
            'label' => [
                'formatted_value'   => __('Sales Channel'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'align'             => 'left'
            ],
            'label_minified' => [
                'formatted_value'   => __('Sales Channel'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'align'             => 'left'
            ]
        ];

        $refundsColumns = [
            'refunds'          => [
                'formatted_value'   => __('Refunds'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'refunds',
            ],
            'refunds_minified' => [
                'formatted_value'   => __('Refunds'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'refunds',
            ],
            'refunds_delta'    => [
                'currency_type'     => 'always',
                'data_display_type' => 'always',
                'formatted_value'   => 'Δ 1Y',
                'tooltip'           => $deltaLabel,
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'refunds',
            ]
        ];

        $invoicesColumns = [
            'invoices'          => [
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
            'invoices_delta'    => [
                'currency_type'     => 'always',
                'data_display_type' => 'always',
                'formatted_value'   => 'Δ 1Y',
                'tooltip'           => $deltaLabel,
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'invoices',
            ]
        ];

        $salesGrpCurrency = [
            'sales_grp_currency'          => [
                'formatted_value'   => __('Sales'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_grp_currency',
            ],
            'sales_grp_currency_minified' => [
                'formatted_value'   => __('Sales'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_grp_currency',
            ],
            'sales_grp_currency_delta'    => [
                'formatted_value'   => 'Δ 1Y',
                'currency_type'     => 'always',
                'tooltip'           => $deltaLabel,
                'data_display_type' => 'always',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_grp_currency',
            ],
        ];

        $columns = $salesChannelColumns;
        $columns = array_merge($columns, $refundsColumns);
        $columns = array_merge($columns, $invoicesColumns);
        $columns = array_merge($columns, $salesGrpCurrency);

        return [
            'slug'    => $group->slug,
            'columns' => $columns
        ];
    }
}
