<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Mar 2025 10:32:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardHeaderInvoiceCategoriesSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;


    public function toArray($request): array
    {
        /** @var Organisation $organisation */
        $organisation = $this;




        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => __('Category')
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => __('Category')
                ]
            ],
            [
                'refunds' => [
                    'formatted_value' => __('Refunds')
                ]
            ],
            [
                'refunds_minified' => [
                    'formatted_value' => __('Refunds')
                ]
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
                'sales_shop_currency' => [
                    'formatted_value' => __('Sales')
                ]
            ],
            [
                'sales_org_currency' => [
                    'formatted_value' => __('Sales')
                ]
            ],
            [
                'sales_shop_currency_minified' => [
                    'formatted_value' => __('Sales')
                ]
            ],
            [
                'sales_org_currency_minified' => [
                    'formatted_value' => __('Sales')
                ]
            ],
        );


        return [
            'slug'    => $organisation->slug,
            'columns' => $columns


        ];
    }
}
