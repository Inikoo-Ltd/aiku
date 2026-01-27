<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Thu, 27 Nov 2025 09:59:21 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardHeaderPlatformSalesResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Platform|Shop $model */
        $model = $this->resource;

        $deltaTooltip = __('Change versus 1 Year ago');

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

        $newChannelsColumns = [
            'channels' => [
                'formatted_value'   => __('Channels'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'channels',
                'information'       => 'This is only showing opened channels' // Todo: In FE
            ],
            'channels_minified' => [
                'formatted_value'   => __('Channels'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'channels'
            ]
        ];

        $newPortfoliosColumns = [
            'portfolios' => [
                'formatted_value'   => __('Portfolios'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'portfolios'
            ],
            'portfolios_minified' => [
                'formatted_value'   => __('Portfolios'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'portfolios'
            ]
        ];

        $newCustomersColumns = [
            'customers' => [
                'formatted_value'   => __('Customers'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'customers'
            ],
            'customers_minified' => [
                'formatted_value'   => __('Customers'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'customers'
            ]
        ];

        $newCustomerClientsColumns = [
            'customer_clients' => [
            'formatted_value'   => __('Customer Clients'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'customer_clients'
            ],
            'customer_clients_minified' => [
                'formatted_value'   => __('Customer Clients'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'customer_clients'
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
//            'invoices_delta' => [
//                'formatted_value'   => 'Δ 1Y',
//                'tooltip'           => $deltaTooltip,
//                'currency_type'     => 'always',
//                'data_display_type' => 'always',
//                'sortable'          => true,
//                'align'             => 'right',
//                'scope'             => 'invoices'
//            ]
        ];

        $salesColumns = [
            'sales' => [
                'formatted_value'   => __('Sales'),
                'currency_type'     => 'shop',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales'
            ],
            'sales_minified' => [
                'formatted_value'   => __('Sales'),
                'currency_type'     => 'shop',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales'
            ],
//            'sales_delta' => [
//                'formatted_value'   => 'Δ 1Y',
//                'tooltip'           => $deltaTooltip,
//                'currency_type'     => 'shop',
//                'data_display_type' => 'always',
//                'sortable'          => true,
//                'align'             => 'right',
//                'scope'             => 'sales'
//            ]
        ];

        $salesOrgCurrencyColumns = [
            'sales_org_currency' => [
                'formatted_value'   => __('Sales'),
                'currency_type'     => 'org',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_org_currency'
            ],
            'sales_org_currency_minified' => [
                'formatted_value'   => __('Sales'),
                'currency_type'     => 'org',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_org_currency'
            ],
//            'sales_org_currency_delta' => [
//                'formatted_value'   => 'Δ 1Y',
//                'tooltip'           => $deltaTooltip,
//                'currency_type'     => 'org',
//                'data_display_type' => 'always',
//                'sortable'          => true,
//                'align'             => 'right',
//                'scope'             => 'sales_org_currency'
//            ],
        ];

        $salesGrpCurrencyColumns = [
            'sales_grp_currency' => [
                'formatted_value'   => __('Sales'),
                'currency_type'     => 'grp',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_grp_currency'
            ],
            'sales_grp_currency_minified' => [
                'formatted_value'   => __('Sales'),
                'currency_type'     => 'grp',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_grp_currency'
            ],
//            'sales_grp_currency_delta' => [
//                'formatted_value'   => 'Δ 1Y',
//                'tooltip'           => $deltaTooltip,
//                'currency_type'     => 'grp',
//                'data_display_type' => 'always',
//                'sortable'          => true,
//                'align'             => 'right',
//                'scope'             => 'sales_grp_currency'
//            ]
        ];

        $salesPercentage = [
            'sales_percentage' => [
                'formatted_value'   => '%',
                'currency_type'     => 'always',
                'data_display_type' => 'always',
                'align'             => 'right',
                'scope'             => 'sales_percentage'
            ]
        ];

        $columns = array_merge(
            $columns,
            $newChannelsColumns,
            $newPortfoliosColumns,
            $newCustomersColumns,
            $newCustomerClientsColumns,
            $invoicesColumns,
            $salesColumns,
            $salesOrgCurrencyColumns,
            $salesGrpCurrencyColumns,
            $salesPercentage
        );

        return [
            'slug' => $model->slug,
            'columns' => $columns
        ];
    }
}
