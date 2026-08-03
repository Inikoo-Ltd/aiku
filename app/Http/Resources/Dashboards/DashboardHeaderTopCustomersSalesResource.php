<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardHeaderTopCustomersSalesResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Group|Organisation|Shop $model */
        $model = $this->resource;

        $columns = [
            'label' => [
                'formatted_value'   => __('Customer'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'align'             => 'left',
                'frozen'            => true,
                'alignFrozen'       => 'left',
            ],
            'label_minified' => [
                'formatted_value'   => __('Customer'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'align'             => 'left',
                'frozen'            => true,
                'alignFrozen'       => 'left',
            ],
            'reference' => [
                'formatted_value'   => __('Reference'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'align'             => 'left',
            ],
            'invoices' => [
                'formatted_value'   => __('Invoices'),
                'currency_type'     => 'always',
                'data_display_type' => 'always',
                'sortable'          => true,
                'align'             => 'right',
            ],
            'last_invoiced_at' => [
                'formatted_value'   => __('Last invoice'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'align'             => 'right',
            ],
        ];

        if ($model instanceof Shop) {
            $columns['sales'] = [
                'formatted_value'   => __('Sales'),
                'currency_type'     => 'shop',
                'data_display_type' => 'always',
                'sortable'          => true,
                'align'             => 'right',
            ];
        }

        $columns['sales_org_currency'] = [
            'formatted_value'   => __('Sales'),
            'currency_type'     => 'org',
            'data_display_type' => 'always',
            'sortable'          => true,
            'align'             => 'right',
        ];

        if ($model instanceof Group) {
            $columns['sales_grp_currency'] = [
                'formatted_value'   => __('Sales'),
                'currency_type'     => 'grp',
                'data_display_type' => 'always',
                'sortable'          => true,
                'align'             => 'right',
            ];
        }

        return [
            'slug'    => $model->slug,
            'columns' => $columns,
        ];
    }
}
