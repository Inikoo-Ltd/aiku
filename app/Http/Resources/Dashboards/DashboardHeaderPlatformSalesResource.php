<?php

namespace App\Http\Resources\Dashboards;

use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardHeaderPlatformSalesResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Shop|Platform $model */
        $model = $this->resource;

        $columns = [
            'label' => [
                'formatted_value'   => __('Platform'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'align'             => 'left'
            ]
        ];

        $invoicesColumns = [
            'invoices' => [
                'formatted_value'   => __('Invoices'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'invoices'
            ]
        ];

        $newChannelsColumns = [
            'new_channels' => [
                'formatted_value'   => __('Channels'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'new_channels'
            ]
        ];

        $newCustomersColumns = [
            'new_customers' => [
                'formatted_value'   => __('Customers'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'new_customers'
            ]
        ];

        $newPortfoliosColumns = [
            'new_portfolios' => [
                'formatted_value'   => __('Portfolios'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'new_portfolios'
            ]
        ];

        $newCustomerClientColumns = [
            'new_customer_client' => [
                'formatted_value'   => __('Customer Client'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'new_customer_client'
            ]
        ];

        $salesColumns = [
            'sales' => [
                'formatted_value'   => __('Sales'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales'
            ]
        ];

        $columns = array_merge(
            $columns,
            $invoicesColumns,
            $newChannelsColumns,
            $newCustomersColumns,
            $newPortfoliosColumns,
            $newCustomerClientColumns
        );

        if ($model instanceof Shop) {
            $columns = array_merge(
                $columns,
                $salesColumns,
                [
                    'sales_percentage' => [
                        'formatted_value'   => '',
                        'currency_type'     => 'always',
                        'data_display_type' => 'full',
                        'align'             => 'right',
                        'scope'             => 'sales_percentage'
                    ]
                ]
            );
        }

        return [
            'slug' => $model->slug,
            'columns' => $columns
        ];
    }
}
