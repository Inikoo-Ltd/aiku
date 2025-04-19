<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Mar 2025 14:51:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\SysAdmin\Group;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardHeaderOrganisationsInvoiceCategoriesSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;


    public function toArray($request): array
    {
        /** @var Group $group */
        $group = $this;


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
            'slug'    => $group->slug,
            'columns' => $columns


        ];
    }
}
