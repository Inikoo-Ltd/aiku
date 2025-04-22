<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Mar 2025 13:08:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardShopSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;


    public function toArray($request): array
    {
        /** @var Shop $shop */
        $shop = $this;

        $routeTargets = [
            'invoices' => [
                    'route_target' => $shop->type == ShopTypeEnum::FULFILMENT ? [
                        'name' => 'grp.org.fulfilments.show.operations.invoices.all.index',
                        'parameters' => [
                            'organisation' => $shop->organisation->slug,
                            'fulfilment' => $shop->fulfilment->slug,
                        ],
                    ] : [
                        'name' => 'grp.org.shops.show.dashboard.invoices.index',
                        'parameters' => [
                            'organisation' => $shop->organisation->slug,
                            'shop' => $shop->slug,
                        ],
                ],
                'key_interval' => 'between[date]', // this is ?between[date] in url
            ],
            'registrations' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.crm.customers.index',
                    'parameters' => [
                        'organisation' => $shop->organisation->slug,
                        'shop' => $shop->slug,
                        'tab' => 'customers',
                    ],
                ],
                'key_interval' => 'between[registered_at]',
            ],
            'shops' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.dashboard.show',
                    'parameters' => [
                        'organisation' => $shop->organisation->slug,
                        'shop' => $shop->slug,
                    ],
                ],
            ],
        ];

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $shop->name,
                    'align'           => 'left',
                    ...$routeTargets['shops']
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => $shop->code,
                    'tooltip'         => $shop->name,
                    'align'           => 'left',
                    ...$routeTargets['shops']
                ]
            ],
            $this->getDashboardTableColumn($shop->salesIntervals, 'baskets_created_shop_currency'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'baskets_created_shop_currency_minified'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'baskets_created_shop_currency_delta'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'baskets_created_org_currency'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'baskets_created_org_currency_minified'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'baskets_created_org_currency_delta'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'invoices', $routeTargets['invoices']),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'invoices_minified', $routeTargets['invoices']),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'invoices_delta'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'registrations', $routeTargets['registrations']),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'registrations_minified', $routeTargets['registrations']),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'registrations_delta'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'sales_shop_currency'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'sales_shop_currency_minified'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'sales_shop_currency_delta'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'sales_org_currency'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'sales_org_currency_minified'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'sales_org_currency_delta'),
        );


        return [
            'slug'    => $shop->slug,
            'state'   => $shop->state == ShopStateEnum::OPEN ? 'active' : 'inactive',
            'columns' => $columns


        ];
    }
}
