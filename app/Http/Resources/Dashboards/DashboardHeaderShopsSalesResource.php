<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Mar 2025 13:08:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardHeaderShopsSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;


    public function toArray($request): array
    {
        /** @var Organisation|Group $model */
        $model = $this->resource;

        $deltaLabel = __('Change versus 1 Year ago');


        $inBasketLabel = __('In basket');


        $shopColumns = [

            'label' => [
                'formatted_value'   => __('Shop'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'align'             => 'left'
            ],


            'label_minified' => [
                'formatted_value'   => __('Shop'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'align'             => 'left'
            ]

        ];

        $basketShopCurrency = [

            'baskets_created_shop_currency' => [
                'formatted_value'   => $inBasketLabel,
                'currency_type'     => 'shop',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'baskets_created_shop_currency',
            ],


            'baskets_created_shop_currency_minified' => [
                'formatted_value'   => $inBasketLabel,
                'currency_type'     => 'shop',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'baskets_created_shop_currency',
            ],


        ];

        $basketOrgCurrency = [

            'baskets_created_org_currency' => [
                'formatted_value'   => $inBasketLabel,
                'currency_type'     => 'org',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'baskets_created_org_currency',
            ],

            'baskets_created_org_currency_minified' => [
                'formatted_value'   => $inBasketLabel,
                'currency_type'     => 'org',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'baskets_created_org_currency',
            ],

        ];

        $basketGrpCurrency = [

            'baskets_created_grp_currency'          => [
                'formatted_value'   => $inBasketLabel,
                'currency_type'     => 'grp',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'baskets_created_grp_currency',
            ],
            'baskets_created_grp_currency_minified' => [
                'formatted_value'   => $inBasketLabel,
                'currency_type'     => 'grp',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'baskets_created_grp_currency',
            ],
        ];

        $registrationColumns = [

            'registrations' => [
                'formatted_value'   => __('Registrations'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'registrations',
            ],

            'registrations_minified' => [
                'formatted_value'   => __('Registrations'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'registrations',
            ],
            'registrations_delta'    => [
                'currency_type'     => 'always',
                'data_display_type' => 'always',
                'formatted_value'   => 'Δ 1Y',
                'tooltip'           => $deltaLabel,
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'registrations',
            ],
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

        $salesShopCurrency = [

            'sales_shop_currency'          => [
                'currency_type'     => 'shop',
                'data_display_type' => 'full',
                'formatted_value'   => __('Sales'),
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_shop_currency',
            ],
            'sales_shop_currency_minified' => [
                'currency_type'     => 'shop',
                'data_display_type' => 'minified',
                'formatted_value'   => __('Sales'),
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_shop_currency',
            ],
            'sales_shop_currency_delta'    => [
                'currency_type'     => 'shop',
                'data_display_type' => 'always',
                'formatted_value'   => 'Δ 1Y',
                'tooltip'           => $deltaLabel,
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_shop_currency',
            ],

        ];

        $salesOrgCurrency = [

            'sales_org_currency'          => [
                'formatted_value'   => __('Sales'),
                'currency_type'     => 'org',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_org_currency',
            ],
            'sales_org_currency_minified' => [
                'currency_type'     => 'org',
                'data_display_type' => 'minified',
                'formatted_value'   => __('Sales'),
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_org_currency',
            ],
            'sales_org_currency_delta'    => [
                'currency_type'     => 'org',
                'data_display_type' => 'always',
                'formatted_value'   => 'Δ 1Y',
                'tooltip'           => $deltaLabel,
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_org_currency',
            ],
        ];

        $salesGrpCurrency = [

            'sales_grp_currency'          => [
                'formatted_value'   => __('Sales'),
                'currency_type'     => 'grp',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_grp_currency',
            ],
            'sales_grp_currency_minified' => [
                'currency_type'     => 'grp',
                'data_display_type' => 'minified',
                'formatted_value'   => __('Sales'),
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_grp_currency',
            ],
            'sales_grp_currency_delta'    => [
                'currency_type'     => 'grp',
                'data_display_type' => 'always',
                'formatted_value'   => 'Δ 1Y',
                'tooltip'           => $deltaLabel,
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => 'sales_grp_currency',
            ],
        ];


        $columns = $shopColumns;


        if ($model instanceof Organisation) {
            $columns = array_merge($columns, $basketShopCurrency);
        }

        $columns = array_merge($columns, $basketOrgCurrency);

        if ($model instanceof Group) {
            $columns = array_merge($columns, $basketGrpCurrency);
        }

        $columns = array_merge($columns, $registrationColumns);
        $columns = array_merge($columns, $invoicesColumns);

        if ($model instanceof Organisation) {
            $columns = array_merge($columns, $salesShopCurrency);
        }

        $columns = array_merge($columns, $salesOrgCurrency);

        if ($model instanceof Group) {
            $columns = array_merge($columns, $salesGrpCurrency);
        }


        return [
            'slug'    => $model->slug,
            'columns' => $columns
        ];
    }
}
