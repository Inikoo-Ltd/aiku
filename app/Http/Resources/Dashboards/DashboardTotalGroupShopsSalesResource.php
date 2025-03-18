<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Mar 2025 13:08:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\SysAdmin\Group;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalGroupShopsSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;


    public function toArray($request): array
    {
        /** @var Group $group */
        $group = $this;


        $baskets_created_grp_currency       = $this->getDashboardTableColumn($group->salesIntervals, 'baskets_created_grp_currency');
        $baskets_created_grp_currency_delta = $this->getDashboardTableColumn($group->salesIntervals, 'baskets_created_grp_currency_delta');

        $baskets_created_org_currency       = [
            'baskets_created_org_currency' => $baskets_created_grp_currency['baskets_created_grp_currency']
        ];
        $baskets_created_org_currency_delta = [
            'baskets_created_org_currency_delta' => $baskets_created_grp_currency_delta['baskets_created_grp_currency_delta']
        ];

        $baskets_created_grp_currency_minified = $this->getDashboardTableColumn($group->salesIntervals, 'baskets_created_grp_currency_minified');
        $baskets_created_org_currency_minified = [
            'baskets_created_org_currency_minified' => $baskets_created_grp_currency_minified['baskets_created_grp_currency_minified']
        ];

        $sales_grp_currency       = $this->getDashboardTableColumn($group->salesIntervals, 'sales_grp_currency');
        $sales_grp_currency_delta = $this->getDashboardTableColumn($group->salesIntervals, 'sales_grp_currency_delta');

        $sales_org_currency = [
            'sales_org_currency' => $sales_grp_currency['sales_grp_currency']
        ];

        $sales_org_currency_delta = [
            'sales_org_currency_delta' => $sales_grp_currency_delta['sales_grp_currency_delta']
        ];


        $sales_grp_currency_minified = $this->getDashboardTableColumn($group->salesIntervals, 'sales_grp_currency_minified');
        $sales_org_currency_minified = [
            'sales_org_currency_minified' => $sales_grp_currency_minified['sales_grp_currency_minified']
        ];

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $group->name
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => $group->code,
                    'tooltip'         => $group->name
                ]
            ],
            $baskets_created_org_currency,
            $baskets_created_org_currency_minified,
            $baskets_created_org_currency_delta,
            $baskets_created_grp_currency,
            $baskets_created_grp_currency_minified,
            $baskets_created_grp_currency_delta,
            $this->getDashboardTableColumn($group->orderingIntervals, 'invoices'),
            $this->getDashboardTableColumn($group->orderingIntervals, 'invoices_minified'),
            $this->getDashboardTableColumn($group->orderingIntervals, 'invoices_delta'),
            $sales_org_currency,
            $sales_org_currency_minified,
            $sales_org_currency_delta,
            $sales_grp_currency,
            $sales_grp_currency_minified,
            $sales_grp_currency_delta
        );


        return [
            'slug'    => $group->slug,
            'columns' => $columns
        ];
    }
}
