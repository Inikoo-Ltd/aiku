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

        $routeTargets = [
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.overview.accounting.invoices.index',
                    'parameters' => [],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'registrations' => [
                'route_target' => [
                    'name' => 'grp.overview.crm.customers.index',
                    'parameters' => [],
                    'key_date_filter' => 'between[registered_at]',
                ],
            ],
            'inBasket' => [
                'route_target' => [
                    'name' => 'grp.overview.ordering.orders_in_basket.index',
                    'parameters' => [],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'group' => [
                'route_target' => [
                    'name' => 'grp.dashboard.show',
                    'parameters' => [],
                ],
            ],
        ];


        $baskets_created_grp_currency       = $this->getDashboardTableColumn($group->salesIntervals, 'baskets_created_grp_currency', $routeTargets['inBasket']);
        $baskets_created_org_currency       = [
            'baskets_created_org_currency' => $baskets_created_grp_currency['baskets_created_grp_currency']
        ];

        $baskets_created_grp_currency_minified = $this->getDashboardTableColumn($group->salesIntervals, 'baskets_created_grp_currency_minified', $routeTargets['inBasket']);
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
                    'formatted_value' => $group->name,
                    'align'           => 'left',
                    ...$routeTargets['group']
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => $group->code,
                    'tooltip'         => $group->name,
                    'align'           => 'left',
                    ...$routeTargets['group']
                ]
            ],
            $baskets_created_org_currency,
            $baskets_created_org_currency_minified,
            $baskets_created_grp_currency,
            $baskets_created_grp_currency_minified,
            $this->getDashboardTableColumn($group->orderingIntervals, 'registrations', $routeTargets['registrations']),
            $this->getDashboardTableColumn($group->orderingIntervals, 'registrations_minified', $routeTargets['registrations']),
            $this->getDashboardTableColumn($group->orderingIntervals, 'registrations_delta'),
            $this->getDashboardTableColumn($group->orderingIntervals, 'invoices', $routeTargets['invoices']),
            $this->getDashboardTableColumn($group->orderingIntervals, 'invoices_minified', $routeTargets['invoices']),
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
