<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Mar 2025 10:32:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardHeaderShopsInvoiceCategoriesSalesResource extends JsonResource
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
                    'formatted_value' => __('Category'),
                    'align' => 'left'
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => __('Category'),
                    'align' => 'left'
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
                'refunds_delta' => $deltaLabel
            ],
            [
                'invoices' => [
                    'formatted_value' => __('Invoices')
                ]
            ],
            [
                'invoices_delta' => $deltaLabel
            ],
            [
                'invoices_minified' => [
                    'formatted_value' => __('Invoices')
                ]
            ],
            [
                'sales_invoice_category_currency' => [
                    'formatted_value' => __('Sales')
                ]
            ],
            [
                'sales_invoice_category_currency_minified' => [
                    'formatted_value' => __('Sales')
                ]
            ],
            [
                'sales_invoice_category_currency_delta' => $deltaLabel,
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
