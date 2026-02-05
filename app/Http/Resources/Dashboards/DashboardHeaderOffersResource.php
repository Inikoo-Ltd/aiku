<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 19 Nov 2025 12:19:21 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Models\Catalogue\Shop;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardHeaderOffersResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Shop $model */
        $model = $this->resource;

        $deltaTooltip = __('Change versus 1 Year ago');

        return [
            'slug'    => $model->slug,
            'columns' => [
                'label'     => [
                    'formatted_value'   => __('Name'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'align'             => 'left'
                ],
                'label_minified'     => [
                    'formatted_value'   => __('Name'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',
                    'align'             => 'left'
                ],
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
                ],
                'orders'    => [
                    'formatted_value'   => __('Orders'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'orders'
                ],
                'orders_minified'    => [
                    'formatted_value'   => __('Orders'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'orders'
                ],
                'invoices'    => [
                    'formatted_value'   => __('Invoices'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'invoices'
                ],
                'invoices_minified'    => [
                    'formatted_value'   => __('Invoices'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'invoices'
                ],
                'invoices_delta' => [
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $deltaTooltip,
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'invoices'
                ],
                'sales' => [
                    'formatted_value'   => __('Sales'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'invoices'
                ],
                'sales_minified' => [
                    'formatted_value'   => __('Sales'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'invoices'
                ],
                'sales_delta' => [
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $deltaTooltip,
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'invoices'
                ],
            ]
        ];
    }
}
