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
                    'formatted_value'   => $inBasketLabel,
                    'currency_type'     => 'org',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'scope'             => 'baskets_created_org_currency',
                ]
            ],
            [
                'baskets_created_org_currency_minified' => [
                    'formatted_value'   => $inBasketLabel,
                    'currency_type'     => 'org',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'scope'             => 'baskets_created_org_currency',
                ]
            ],
            [
                'baskets_created_grp_currency' => [
                    'formatted_value'   => $inBasketLabel,
                    'currency_type'     => 'grp',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'scope'             => 'baskets_created_grp_currency',
                ]
            ],
            [
                'baskets_created_grp_currency_minified' => [
                    'formatted_value'   => $inBasketLabel,
                    'currency_type'     => 'grp',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'scope'             => 'baskets_created_grp_currency',
                ]
            ],
            [
                'registrations' => [
                    'formatted_value'   => __('Registrations'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'scope'             => 'registrations',
                ]
            ],
            [
                'registrations_minified' => [
                    'formatted_value'   => __('Registrations'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'scope'             => 'registrations',
                ]
            ],
            [
                'registrations_delta' => [
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $labelDelta,
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'registrations_delta',
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
                    'tooltip'           => $labelDelta,
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'invoices_delta',
                ]
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
                    'tooltip'           => $labelDelta,
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'sales_org_currency_delta',
                ],
            ],
            [
                'sales_grp_currency' => [
                    'formatted_value'   => __('Sales'),
                    'currency_type'     => 'grp',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'scope'             => 'sales_grp_currency',
                ]
            ],
            [
                'sales_grp_currency_minified' => [
                    'formatted_value'   => __('Sales'),
                    'currency_type'     => 'grp',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'scope'             => 'sales_grp_currency',
                ]
            ],
            [
                'sales_grp_currency_delta' => [
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => $labelDelta,
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                    'sortable'          => true,
                    'align'             => 'right',
                    'scope'             => 'sales_grp_currency_delta',
                ],
            ],
        );


        return [
            'slug'    => $organisation->slug,
            'columns' => $columns


        ];
    }
}
