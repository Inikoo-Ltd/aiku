<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Mar 2025 13:08:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardShopSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;


    public function toArray($request): array
    {
        /** @var Shop $shop */
        $shop = $this;

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $shop->name,
                    'align'           => 'left'
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => $shop->code,
                    'tooltip'         => $shop->name,
                    'align'           => 'left'
                ]
            ],
            $this->getDashboardTableColumn($shop->salesIntervals, 'baskets_created_shop_currency'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'baskets_created_shop_currency_minified'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'baskets_created_shop_currency_delta'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'baskets_created_org_currency'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'baskets_created_org_currency_minified'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'baskets_created_org_currency_delta'),
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
