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

class DashboardHeaderInvoiceCategoriesInOrganisationSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;


    public function toArray($request): array
    {
        /** @var Organisation $organisation */
        $organisation = $this;

        $deltaTooltip = __('Change versus 1 Year ago');


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
                    'sortable'          => true,
                    'scope'             => 'refunds',
                ]
            ],
            [
                'refunds_minified' => [
                    'formatted_value'   => __('Refunds'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'scope'             => 'refunds',
                ]
            ],
            [
                'refunds_inverse_delta' => [
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $deltaTooltip,
                    'sortable'          => true,
                    'scope'             => 'refunds',
                ]
            ],
            [
                'invoices' => [
                    'formatted_value'   => __('Invoices'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'scope'             => 'invoices',
                ]
            ],
            [
                'invoices_minified' => [
                    'formatted_value'   => __('Invoices'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'scope'             => 'invoices',
                ]
            ],
            [
                'invoices_delta' => [
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $deltaTooltip,
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'scope'             => 'invoices',
                ]
            ],
            [
                'sales_invoice_category_currency' => [
                    'formatted_value'   => __('Sales'),
                    'currency_type'     => 'category',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'scope'             => 'sales_invoice_category_currency',
                ]
            ],
            [
                'sales_invoice_category_currency_minified' => [
                    'formatted_value'   => __('Sales'),
                    'currency_type'     => 'category',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'scope'             => 'sales_invoice_category_currency',
                ]
            ],
            [
                'sales_invoice_category_currency_delta' => [
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $deltaTooltip,
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'scope'             => 'sales_invoice_category_currency',
                ],
            ],
            [
                'sales_org_currency' => [
                    'formatted_value'   => __('Sales'),
                    'currency_type'     => 'org',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'scope'             => 'sales_org_currency',
                ]
            ],
            [
                'sales_org_currency_minified' => [
                    'formatted_value'   => __('Sales'),
                    'currency_type'     => 'org',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'scope'             => 'sales_org_currency',
                ]
            ],
            [
                'sales_org_currency_delta' => [
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $deltaTooltip,
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'scope'             => 'sales_org_currency',
                ],
            ],
        );


        return [
            'slug'    => $organisation->slug,
            'columns' => $columns


        ];
    }
}
