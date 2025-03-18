<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Mar 2025 13:08:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardHeaderShopsSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;


    public function toArray($request): array
    {
        /** @var Organisation $organisation */
        $organisation = $this;


        $deltaLabel = [
            'formatted_value' => 'Δ 1Y',
            'tooltip'         => __('Change versus 1 Year ago')
        ];

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => __('Shop')
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => __('Shop')
                ]
            ],
            [
                'baskets_created_shop_currency' => [
                    'formatted_value' => __('In basket')
                ]
            ],
            [
                'baskets_created_shop_currency_minified' => [
                    'formatted_value' => __('In basket')
                ]
            ],
            [
                'baskets_created_shop_currency_delta' => $deltaLabel
            ],
            [
                'baskets_created_org_currency' => [
                    'formatted_value' => __('In basket')
                ]
            ],
            [
                'baskets_created_org_currency_minified' => [
                    'formatted_value' => __('In basket')
                ]
            ],
            [
                'baskets_created_org_currency_delta' => $deltaLabel
            ],
            [
                'invoices' => [
                    'formatted_value' => __('Invoices')
                ]
            ],
            [
                'invoices_minified' => [
                    'formatted_value' => __('Invoices')
                ]
            ],
            [
                'invoices_delta' => $deltaLabel
            ],
            [
                'sales_shop_currency' => [
                    'formatted_value' => __('Sales')
                ]
            ],
            [
                'sales_shop_currency_minified' => [
                    'formatted_value' => __('Sales')
                ]
            ],
            [
                'sales_shop_currency_delta' => $deltaLabel,
            ],
            [
                'sales_org_currency' => [
                    'formatted_value' => __('Sales')
                ]
            ],
            [
                'sales_org_currency_minified' => [
                    'formatted_value' => __('Sales')
                ]
            ],
            [
                'sales_org_currency_delta' => $deltaLabel,
            ],
        );


        return [
            'slug'    => $organisation->slug,
            'columns' => $columns


        ];
    }
}
