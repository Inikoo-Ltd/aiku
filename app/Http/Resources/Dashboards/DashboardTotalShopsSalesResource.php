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

class DashboardTotalShopsSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;


    public function toArray($request): array
    {
        /** @var Organisation $organisation */
        $organisation = $this;


        $baskets_created_org_currency = $this->getDashboardTableColumn($organisation->salesIntervals, 'baskets_created_org_currency');
        $baskets_created_org_currency_delta = $this->getDashboardTableColumn($organisation->salesIntervals, 'baskets_created_org_currency_delta');

        $baskets_created_shop_currency = [
            'baskets_created_shop_currency' => $baskets_created_org_currency['baskets_created_org_currency']
        ];
        $baskets_created_shop_currency_delta = [
            'baskets_created_shop_currency_delta' => $baskets_created_org_currency_delta['baskets_created_org_currency_delta']
        ];

        $baskets_created_org_currency_minified = $this->getDashboardTableColumn($organisation->salesIntervals, 'baskets_created_org_currency_minified');
        $baskets_created_shop_currency_minified = [
            'baskets_created_shop_currency_minified' => $baskets_created_org_currency_minified['baskets_created_org_currency_minified']
        ];

        $sales_org_currency = $this->getDashboardTableColumn($organisation->salesIntervals, 'sales_org_currency');
        $sales_org_currency_delta = $this->getDashboardTableColumn($organisation->salesIntervals, 'sales_org_currency_delta');

        $sales_shop_currency = [
            'sales_shop_currency' => $sales_org_currency['sales_org_currency']
        ];

        $sales_shop_currency_delta = [
            'sales_shop_currency_delta' => $sales_org_currency_delta['sales_org_currency_delta']
        ];



        $sales_org_currency_minified = $this->getDashboardTableColumn($organisation->salesIntervals, 'sales_org_currency_minified');
        $sales_shop_currency_minified = [
            'sales_shop_currency_minified' => $sales_org_currency_minified['sales_org_currency_minified']
        ];

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $organisation->name,
                    'align'           => 'left'
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => $organisation->code,
                    'tooltip'         => $organisation->name,
                    'align'           => 'left'
                ]
            ],
            $baskets_created_shop_currency,
            $baskets_created_shop_currency_minified,
            $baskets_created_shop_currency_delta,
            $baskets_created_org_currency,
            $baskets_created_org_currency_minified,
            $baskets_created_org_currency_delta,
            $this->getDashboardTableColumn($organisation->orderingIntervals, 'invoices'),
            $this->getDashboardTableColumn($organisation->orderingIntervals, 'invoices_minified'),
            $this->getDashboardTableColumn($organisation->orderingIntervals, 'invoices_delta'),
            $sales_shop_currency,
            $sales_shop_currency_minified,
            $sales_shop_currency_delta,
            $sales_org_currency,
            $sales_org_currency_minified,
            $sales_org_currency_delta
        );


        return [
            'slug'    => $organisation->slug,
            'columns' => $columns


        ];
    }
}
