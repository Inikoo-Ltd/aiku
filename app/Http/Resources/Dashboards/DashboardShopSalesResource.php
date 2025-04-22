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
                        'name' => 'grp.org.shops.show.dashboard.fulfilment.index',
                        'parameters' => [
                            'organisation' => $shop->organisation->slug,
                            'shop' => $shop->slug,
                        ],
                    ] : [
                        'name' => 'grp.org.shops.show.dashboard.invoices.index',
                        'parameters' => [
                            'organisation' => $shop->organisation->slug,
                            'shop' => $shop->slug,
                        ],
                ],
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
            $this->getDashboardTableColumn($shop->orderingIntervals, 'registrations'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'registrations_minified'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'registrations_delta'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'invoices'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'invoices_minified'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'invoices_delta'),
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
