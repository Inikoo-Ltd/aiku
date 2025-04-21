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
            'currency_type'     => 'always',
            'data_display_type' => 'always',
            'formatted_value'   => 'Δ 1Y',
            'tooltip'           => __('Change versus 1 Year ago')
        ];

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value'   => __('Category'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'align'             => 'left'
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value'   => __('Category'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',
                    'align'             => 'left'
                ]
            ],
            [
                'refunds' => [
                    'formatted_value'   => __('Refunds'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                ]
            ],
            [
                'refunds_minified' => [
                    'formatted_value'   => __('Refunds'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',
                ]
            ],
            [
                'refunds_delta' => $deltaLabel
            ],
            [
                'invoices' => [
                    'formatted_value'   => __('Invoices'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                ]
            ],
            [
                'invoices_delta' => $deltaLabel
            ],
            [
                'invoices_minified' => [
                    'formatted_value'   => __('Invoices'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',

                ]
            ],
            [
                'sales_invoice_category_currency' => [
                    'formatted_value' => __('Sales'),
                    'currency_type'     => 'category',
                    'data_display_type' => 'full',
                ]
            ],
            [
                'sales_invoice_category_currency_minified' => [
                    'formatted_value' => __('Sales'),
                    'currency_type'     => 'category',
                    'data_display_type' => 'minified',
                ]
            ],
            [
                'sales_invoice_category_currency_delta' => $deltaLabel,
            ],
            [
                'sales_org_currency' => [
                    'formatted_value' => __('Sales'),
                    'currency_type'     => 'org',
                    'data_display_type' => 'full',
                ]
            ],
            [
                'sales_org_currency_minified' => [
                    'formatted_value' => __('Sales'),
                    'currency_type'     => 'org',
                    'data_display_type' => 'minified',
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
