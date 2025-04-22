<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Mar 2025 14:51:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardHeaderOrganisationsSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;


    public function toArray($request): array
    {
        /** @var Organisation $organisation */
        $organisation = $this;

        $inBasketLabel = __('In basket');

        $labelDelta = __('Change versus 1 Year ago');

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value'   => __('Organisation'),
                    'align'             => 'left',
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value'   => __('Organisation'),
                    'align'             => 'left',
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',
                ]
            ],
            [
                'baskets_created_org_currency' => [
                    'formatted_value'   => $inBasketLabel.' a',
                    'currency_type'     => 'org',
                    'data_display_type' => 'full',
                ]
            ],
            [
                'baskets_created_org_currency_minified' => [
                    'formatted_value'   => $inBasketLabel.' b',
                    'currency_type'     => 'org',
                    'data_display_type' => 'minified',
                ]
            ],
            [
                'baskets_created_org_currency_delta' => [
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $labelDelta,
                    'currency_type'     => 'org',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'align'             => 'right'
                ]
            ],
            [
                'baskets_created_grp_currency' => [
                    'formatted_value'   => $inBasketLabel.' c',
                    'currency_type'     => 'grp',
                    'data_display_type' => 'full',
                ]
            ],
            [
                'baskets_created_grp_currency_minified' => [
                    'formatted_value'   => $inBasketLabel.' d',
                    'currency_type'     => 'grp',
                    'data_display_type' => 'minified',
                ]
            ],
            [
                'baskets_created_grp_currency_delta' => [
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $labelDelta,
                    'currency_type'     => 'grp',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'align'             => 'right'
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
                    'tooltip'           => $labelDelta,
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'align'             => 'right'
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
                    'tooltip'           => $labelDelta,
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'align'             => 'right'
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
                    'tooltip'           => $labelDelta,
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'align'             => 'right'
                ],
            ],
        );


        return [
            'slug'    => $organisation->slug,
            'columns' => $columns


        ];
    }
}
