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


        $routeTargets = [
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.org.accounting.invoices.index',
                    'parameters' => [
                        'organisation' => $this->slug,
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'inBasket' => [
                'route_target' => [
                    'name' => 'grp.org.overview.orders_in_basket.index',
                    'parameters' => [
                        'organisation' => $this->slug,
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'registrations' => [
                'route_target' => [
                    'name' => 'grp.org.overview.customers.index',
                    'parameters' => [
                        'organisation' => $this->slug,
                    ],
                    'key_date_filter' => 'between[registered_at]',
                ],
            ],
            'organisation' => [
                'route_target' => [
                    'name' => 'grp.org.dashboard.show',
                    'parameters' => [
                        'organisation' => $this->slug,
                    ],
                ],
            ],
        ];

        $baskets_created_org_currency = $this->getDashboardTableColumn($organisation->salesIntervals, 'baskets_created_org_currency', $routeTargets['inBasket']);
        $baskets_created_org_currency_delta = $this->getDashboardTableColumn($organisation->salesIntervals, 'baskets_created_org_currency_delta');

        $baskets_created_shop_currency = [
            'baskets_created_shop_currency' => $baskets_created_org_currency['baskets_created_org_currency']
        ];
        $baskets_created_shop_currency_delta = [
            'baskets_created_shop_currency_delta' => $baskets_created_org_currency_delta['baskets_created_org_currency_delta']
        ];

        $baskets_created_org_currency_minified = $this->getDashboardTableColumn($organisation->salesIntervals, 'baskets_created_org_currency_minified', $routeTargets['inBasket']);
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
                    'formatted_value'   => $organisation->name,
                    'align'             => 'left',
                    'data_display_type' => 'full',
                    ...$routeTargets['organisation'],
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value'   => $organisation->code,
                    'tooltip'           => $organisation->name,
                    'align'             => 'left',
                    'data_display_type' => 'minified',
                    ...$routeTargets['organisation'],
                ]
            ],
            $baskets_created_shop_currency,
            $baskets_created_shop_currency_minified,
            $baskets_created_shop_currency_delta,
            $baskets_created_org_currency,
            $baskets_created_org_currency_minified,
            $baskets_created_org_currency_delta,
            $this->getDashboardTableColumn($organisation->orderingIntervals, 'registrations', $routeTargets['registrations']),
            $this->getDashboardTableColumn($organisation->orderingIntervals, 'registrations_minified', $routeTargets['registrations']),
            $this->getDashboardTableColumn($organisation->orderingIntervals, 'registrations_delta'),
            $this->getDashboardTableColumn($organisation->orderingIntervals, 'invoices', $routeTargets['invoices']),
            $this->getDashboardTableColumn($organisation->orderingIntervals, 'invoices_minified', $routeTargets['invoices']),
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
