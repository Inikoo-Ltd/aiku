<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardHeaderPlatformSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;

    public function toArray($request): array {
        /** @var Shop|Platform $model */
        $model = $this->resource;

        $columns = [
            'label' => [
                'formatted_value'   => __('Platform'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'align'             => 'left'
            ],
            'label_minified' => [
                'formatted_value'   => __('Platform'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
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
            ],
            'invoices_minified' => [
                'formatted_value'   => __('Invoices'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'invoices'
            ],
            'invoices_delta' => [
                'formatted_value'   => 'Δ 1Y',
                'currency_type'     => 'always',
                'data_display_type' => 'always',
                'tooltip'           => __('Change versus 1 Year ago'),
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'invoices'
            ]
        ];

        $newChannelsColumns = [
            'new_channels' => [
                'formatted_value'   => __('New Channels'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'new_channels'
            ],
            'new_channels_minified' => [
                'formatted_value'   => __('New Channels'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'new_channels'
            ],
            'new_channels_delta' => [
                'formatted_value'   => 'Δ 1Y',
                'currency_type'     => 'always',
                'data_display_type' => 'always',
                'tooltip'           => __('Change versus 1 Year ago'),
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'new_channels'
            ]
        ];

        $newCustomersColumns = [
            'new_customers' => [
                'formatted_value'   => __('New Customers'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'new_customers'
            ],
            'new_customers_minified' => [
                'formatted_value'   => __('New Customers'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'new_customers'
            ],
            'new_customers_delta' => [
                'formatted_value'   => 'Δ 1Y',
                'currency_type'     => 'always',
                'data_display_type' => 'always',
                'tooltip'           => __('Change versus 1 Year ago'),
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'new_customers'
            ]
        ];

        $newPortfoliosColumns = [
            'new_portfolios' => [
                'formatted_value'   => __('New Portfolios'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'new_portfolios'
            ],
            'new_portfolios_minified' => [
                'formatted_value'   => __('New Portfolios'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'new_portfolios'
            ],
            'new_portfolios_delta' => [
                'formatted_value'   => 'Δ 1Y',
                'currency_type'     => 'always',
                'data_display_type' => 'always',
                'tooltip'           => __('Change versus 1 Year ago'),
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'new_portfolios'
            ]
        ];

        $newCustomerClientColumns = [
            'new_customer_client' => [
                'formatted_value'   => __('New Customer Client'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'new_customer_client'
            ],
            'new_customer_client_minified' => [
                'formatted_value'   => __('New Customer Client'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'new_customer_client'
            ],
            'new_customer_client_delta' => [
                'formatted_value'   => 'Δ 1Y',
                'currency_type'     => 'always',
                'data_display_type' => 'always',
                'tooltip'           => __('Change versus 1 Year ago'),
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
            ],
            'sales_minified' => [
                'formatted_value'   => __('Sales'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales'
            ],
            'sales_delta' => [
                'formatted_value'   => 'Δ 1Y',
                'currency_type'     => 'always',
                'data_display_type' => 'always',
                'tooltip'           => __('Change versus 1 Year ago'),
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales'
            ]
        ];

        $salesOrgCurrencyColumns = [
            'sales_org_currency' => [
                'formatted_value'   => __('Sales Org Currency'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_org_currency'
            ],
            'sales_org_currency_minified' => [
                'formatted_value'   => __('Sales Org Currency'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_org_currency'
            ],
            'sales_org_currency_delta' => [
                'formatted_value'   => 'Δ 1Y',
                'currency_type'     => 'always',
                'data_display_type' => 'always',
                'tooltip'           => __('Change versus 1 Year ago'),
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_org_currency'
            ]
        ];

        $salesGrpCurrencyColumns = [
            'sales_grp_currency' => [
                'formatted_value'   => __('Sales Grp Currency'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_grp_currency'
            ],
            'sales_grp_currency_minified' => [
                'formatted_value'   => __('Sales Grp Currency'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_grp_currency'
            ],
            'sales_grp_currency_delta' => [
                'formatted_value'   => 'Δ 1Y',
                'currency_type'     => 'always',
                'data_display_type' => 'always',
                'tooltip'           => __('Change versus 1 Year ago'),
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_grp_currency'
            ]
        ];

        $columns = array_merge(
            $columns,
            $invoicesColumns,
            $newChannelsColumns,
            $newCustomersColumns,
            $newPortfoliosColumns,
            $newCustomerClientColumns,
            $salesGrpCurrencyColumns
        );

        if ($model instanceof Shop) {
            $columns = array_merge($columns, $salesColumns, $salesOrgCurrencyColumns);
        }

        return [
            'slug' => $model->slug,
            'columns' => $columns
        ];
    }
}
