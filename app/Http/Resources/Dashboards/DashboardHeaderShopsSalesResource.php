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




        $inBasketLabel = __('In basket');

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value'   => __('Shop'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'align'             => 'left'
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value'   => __('Shop'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',
                    'align'             => 'left'
                ]
            ],
            [
                'baskets_created_shop_currency' => [
                    'formatted_value'   => $inBasketLabel.' CCC',
                    'currency_type'     => 'shop',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'align'             => 'right'
                ]
            ],
            [
                'baskets_created_shop_currency_minified' => [
                    'formatted_value'   => '▪ '.__('In basket'),
                    'currency_type'     => 'shop',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'align'             => 'right'
                ]
            ],
            [
                'baskets_created_shop_currency_delta' => [
                    'currency_type'     => 'shop',
                    'data_display_type' => 'always',
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => __('Change versus 1 Year ago'),
                    'sortable'          => true,
                    'align'             => 'right'
                ]
            ],
            [
                'baskets_created_org_currency' => [
                    'formatted_value'   => $inBasketLabel,
                    'currency_type'     => 'org',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'align'             => 'right'
                ]
            ],
            [
                'baskets_created_org_currency_minified' => [
                    'formatted_value'   => $inBasketLabel,
                    'currency_type'     => 'org',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'align'             => 'right'
                ]
            ],
            [
                'baskets_created_org_currency_delta' =>
                    [
                        'data_display_type' => 'always',
                        'currency_type'     => 'org',
                        'formatted_value'   => 'Δ 1Y',
                        'tooltip'           => __('Change versus 1 Year ago'),
                        'sortable'          => true,
                        'align'             => 'right'
                    ]
            ],
            [
                'invoices' => [
                    'formatted_value'   => __('Invoices'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'align'             => 'right'
                ]
            ],
            [
                'invoices_minified' => [
                    'formatted_value'   => __('Invoices'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'minified',
                    'sortable'          => true,
                    'align'             => 'right'
                ]
            ],
            [
                'invoices_delta' => [
                    'currency_type'     => 'always',
                    'data_display_type' => 'always',
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => __('Change versus 1 Year ago'),
                    'sortable'          => true,
                    'align'             => 'right'
                ]
            ],
            [
                'sales_shop_currency' => [
                    'currency_type'     => 'shop',
                    'data_display_type' => 'full',
                    'formatted_value'   => __('Sales'),
                    'sortable'          => true,
                    'align'             => 'right'
                ]
            ],
            [
                'sales_shop_currency_minified' => [
                    'currency_type'     => 'shop',
                    'data_display_type' => 'minified',
                    'formatted_value'   => __('Sales'),
                    'sortable'          => true,
                    'align'             => 'right'
                ]
            ],
            [
                'sales_shop_currency_delta' => [
                    'currency_type'     => 'shop',
                    'data_display_type' => 'always',
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => __('Change versus 1 Year ago'),
                    'sortable'          => true,
                    'align'             => 'right'
                ],
            ],
            [
                'sales_org_currency' => [
                    'formatted_value'   => __('Sales'),
                    'currency_type'     => 'org',
                    'data_display_type' => 'full',
                    'sortable'          => true,
                    'align'             => 'right'
                ]
            ],
            [
                'sales_org_currency_minified' => [
                    'currency_type'     => 'org',
                    'data_display_type' => 'minified',
                    'formatted_value'   => __('Sales'),
                    'sortable'          => true,
                    'align'             => 'right'
                ]
            ],
            [
                'sales_org_currency_delta' => [
                    'currency_type'     => 'org',
                    'data_display_type' => 'always',
                    'formatted_value'   => 'Δ 1Y',
                    'tooltip'           => __('Change versus 1 Year ago'),
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
