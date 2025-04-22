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

class DashboardHeaderInvoiceCategoriesInGroupSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;


    public function toArray($request): array
    {
        /** @var Group $group */
        $group = $this;


        $deltaTooltip = __('Change versus 1 Year ago');

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value'   => __('Category'),
                    'align'             => 'left',
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',

                ]
            ],
            [
                'label_minified' => [
                    'formatted_value'   => __('Category'),
                    'align'             => 'left',
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',
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
                'refunds_delta' => [
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $deltaTooltip,
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                ]
            ],
            [
                'invoices' => [
                    'formatted_value'   => __('Invoices'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                ]
            ],
            [
                'invoices_minified' => [
                    'formatted_value'   => __('Invoices'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',
                ]
            ],
            [
                'invoices_delta' => [
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $deltaTooltip,
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                ]
            ],


            [
                'sales_org_currency' => [
                    'formatted_value'   => __('Sales'),
                    'currency_type'     => 'org',
                    'data_display_type' => 'full',
                ]
            ],
            [
                'sales_org_currency_minified' => [
                    'formatted_value'   => __('Sales'),
                    'currency_type'     => 'org',
                    'data_display_type' => 'minified',
                ]
            ],
            [
                'sales_org_currency_delta' => [
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $deltaTooltip,
                    'currency_type'     => 'org',
                    'data_display_type' => 'always',
                ],
            ],

            [
                'sales_grp_currency' => [
                    'formatted_value'   => __('Sales'),
                    'currency_type'     => 'grp',
                    'data_display_type' => 'full',
                ]
            ],
            [
                'sales_grp_currency_minified' => [
                    'formatted_value'   => __('Sales'),
                    'currency_type'     => 'grp',
                    'data_display_type' => 'minified',
                ]
            ],
            [
                'sales_grp_currency_delta' => [
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $deltaTooltip,
                    'currency_type'     => 'grp',
                    'data_display_type' => 'always',
                ],
            ],

        );


        return [
            'slug'    => $group->slug,
            'columns' => $columns
        ];
    }
}
