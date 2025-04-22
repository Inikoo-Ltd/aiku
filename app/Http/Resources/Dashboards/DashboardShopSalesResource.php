<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Mar 2025 13:08:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $name
 * @property mixed $code
 */
class DashboardShopSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;


    public function toArray($request): array
    {


        $routeTargets = [
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.helpers.redirect_invoices_from_dashboard',
                    'parameters' => [
                        'shop' => $this->id,
                    ],
                ],
            ],
            'registrations' => [
                'route_target' => [
                    'name' => 'grp.helpers.redirect_customers_from_dashboard',
                    'parameters' => [
                        'shop' => $this->id,
                    ],
                ],
            ],
            'shops' => [
                'route_target' => [
                    'name' => 'grp.helpers.redirect_shops_from_dashboard',
                    'parameters' => [
                        'shop' => $this->id,
                    ],
                ],
            ],
        ];

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $this->name,
                    'align'           => 'left',
                    ...$routeTargets['shops']
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => $this->code,
                    'tooltip'         => $this->name,
                    'align'           => 'left',
                    ...$routeTargets['shops']
                ]
            ],
            $this->getDashboardTableColumn($this->salesIntervals, 'baskets_created_shop_currency'),
            $this->getDashboardTableColumn($this->salesIntervals, 'baskets_created_shop_currency_minified'),
            $this->getDashboardTableColumn($this->salesIntervals, 'baskets_created_shop_currency_delta'),
            $this->getDashboardTableColumn($this->salesIntervals, 'baskets_created_org_currency'),
            $this->getDashboardTableColumn($this->salesIntervals, 'baskets_created_org_currency_minified'),
            $this->getDashboardTableColumn($this->salesIntervals, 'baskets_created_org_currency_delta'),
            $this->getDashboardTableColumn($this->orderingIntervals, 'invoices', $routeTargets['invoices']),
            $this->getDashboardTableColumn($this->orderingIntervals, 'invoices_minified', $routeTargets['invoices']),
            $this->getDashboardTableColumn($this->orderingIntervals, 'invoices_delta'),
            $this->getDashboardTableColumn($this->orderingIntervals, 'registrations', $routeTargets['registrations']),
            $this->getDashboardTableColumn($this->orderingIntervals, 'registrations_minified', $routeTargets['registrations']),
            $this->getDashboardTableColumn($this->orderingIntervals, 'registrations_delta'),
            $this->getDashboardTableColumn($this->salesIntervals, 'sales_shop_currency'),
            $this->getDashboardTableColumn($this->salesIntervals, 'sales_shop_currency_minified'),
            $this->getDashboardTableColumn($this->salesIntervals, 'sales_shop_currency_delta'),
            $this->getDashboardTableColumn($this->salesIntervals, 'sales_org_currency'),
            $this->getDashboardTableColumn($this->salesIntervals, 'sales_org_currency_minified'),
            $this->getDashboardTableColumn($this->salesIntervals, 'sales_org_currency_delta'),
        );


        return [
            'slug'    => $this->slug,
            'state'   => $this->state == ShopStateEnum::OPEN ? 'active' : 'inactive',
            'columns' => $columns


        ];
    }
}
