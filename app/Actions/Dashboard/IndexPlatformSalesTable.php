<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Thu, 27 Nov 2025 10:10:26 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Dashboard;

use App\Actions\OrgAction;
use App\Http\Resources\Dashboards\DashboardPlatformSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalPlatformSalesResource;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;

class IndexPlatformSalesTable extends OrgAction
{
    public function handle(Group|Shop $parent): LengthAwarePaginator
    {
        $queryBuilder = QueryBuilder::for(Platform::class);

        if ($parent instanceof Group) {
            $this->buildGroupQuery($queryBuilder, $parent);
        } else {
            $this->buildShopQuery($queryBuilder, $parent);
        }

        return $queryBuilder
            ->defaultSort('platforms.code')
            ->allowedSorts(['code', 'name', 'type'])
            ->withPaginator(null, 1000)
            ->withQueryString();
    }

    private function buildGroupQuery(QueryBuilder $queryBuilder, Group $group): void
    {
        $queryBuilder
            ->where('platforms.group_id', $group->id)
            ->leftJoin('platform_sales_intervals', 'platforms.id', 'platform_sales_intervals.platform_id')
            ->select([
                'platforms.id',
                'platforms.code',
                'platforms.name',
                'platforms.slug',
                'platforms.type',
                'platform_sales_intervals.*'
            ])
            ->selectRaw('\'' . $group->currency->code . '\' as group_currency_code')
            ->where(function ($query) {
                $query->where('platform_sales_intervals.invoices_all', '>', 0)
                    ->orWhere('platform_sales_intervals.new_customers_all', '>', 0)
                    ->orWhere('platform_sales_intervals.new_channels_all', '>', 0)
                    ->orWhere('platform_sales_intervals.sales_grp_currency_all', '>', 0);
            });
    }

    private function buildShopQuery(QueryBuilder $queryBuilder, Shop $shop): void
    {
        $queryBuilder
            ->where('platforms.group_id', $shop->group_id)
            ->leftJoin('platform_shop_sales_intervals', 'platforms.id', 'platform_shop_sales_intervals.platform_id')
            ->where('platform_shop_sales_intervals.shop_id', $shop->id)
            ->select([
                'platforms.id',
                'platforms.code',
                'platforms.name',
                'platforms.slug',
                'platforms.type',
                'platform_shop_sales_intervals.*'
            ])
            ->selectRaw('\'' . $shop->group->currency->code . '\' as group_currency_code')
            ->selectRaw('\'' . $shop->organisation->currency->code . '\' as organisation_currency_code')
            ->selectRaw('\'' . $shop->slug . '\' as shop_slug')
            ->selectRaw('\'' . $shop->organisation->slug . '\' as organisation_slug')
            ->where(function ($query) {
                $query->where('platform_shop_sales_intervals.invoices_all', '>', 0)
                    ->orWhere('platform_shop_sales_intervals.new_customers_all', '>', 0)
                    ->orWhere('platform_shop_sales_intervals.new_channels_all', '>', 0)
                    ->orWhere('platform_shop_sales_intervals.sales_all', '>', 0)
                    ->orWhere('platform_shop_sales_intervals.sales_org_currency_all', '>', 0)
                    ->orWhere('platform_shop_sales_intervals.sales_grp_currency_all', '>', 0);
            });
    }

    public function action(Group|Shop $parent): array
    {
        if ($parent instanceof Group) {
            $this->initialisationFromGroup($parent, []);
        } else {
            $this->initialisationFromShop($parent, []);
        }

        $platforms = $this->handle($parent);

        return json_decode(DashboardPlatformSalesResource::collection($platforms)->toJson(), true);
    }

    public function total(Group|Shop $parent): array
    {
        if ($parent instanceof Group) {
            $this->initialisationFromGroup($parent, []);
        } else {
            $this->initialisationFromShop($parent, []);
        }

        $platforms = $this->handle($parent);

        return json_decode(DashboardTotalPlatformSalesResource::make($platforms)->toJson(), true);
    }
}
