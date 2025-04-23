<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Mar 2025 10:57:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dashboard;

use App\Actions\OrgAction;
use App\Http\Resources\Dashboards\DashboardShopSalesInGroupResource;
use App\Http\Resources\Dashboards\DashboardShopSalesInOrganisationResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;

class IndexShopsSalesTable extends OrgAction
{
    public function handle(Group|Organisation $parent)
    {
        $queryBuilder = QueryBuilder::for(Shop::class);
        $queryBuilder->leftJoin('shop_sales_intervals', 'shops.id', 'shop_sales_intervals.shop_id');
        $queryBuilder->leftJoin('shop_ordering_intervals', 'shops.id', 'shop_ordering_intervals.shop_id');
        $queryBuilder->leftJoin('organisations', 'shops.organisation_id', 'organisations.id');



        if (class_basename($parent) == 'Organisation') {
            $queryBuilder->where('organisation_id', $parent->id);
        } else {
            $queryBuilder->where('shops.group_id', $parent->id);
        }


        $queryBuilder
           ->defaultSort('shops.code')
           ->select([
               'shops.id',
               'shops.currency_id as shop_currency_id',
               'organisations.currency_id as organisation_currency_id',
               'shops.code',
               'shops.name',
               'shops.slug',
               'shops.type',
               'shops.state',
               'shops.organisation_id',
               'organisations.slug as organisation_slug',
               'shop_sales_intervals.*',
               'shop_ordering_intervals.*',
           ]);
        if ($parent instanceof Group) {
            $queryBuilder->selectRaw('\''.$parent->currency->code.'\' as group_currency_code');
        } else {
            $queryBuilder->selectRaw('\''.$parent->group->currency->code.'\' as group_currency_code');
        }


        return $queryBuilder->allowedSorts(['code', 'name', 'type', 'state'])
        ->withPaginator(null)
        ->withQueryString();


    }


    public function action(Group|Organisation $parent): array
    {
        if ($parent instanceof Group) {
            $this->initialisationFromGroup($parent, []);
        } else {
            $this->initialisation($parent, []);
        }
        $shops = $this->handle($parent);

        if ($parent instanceof Group) {
            return json_decode(DashboardShopSalesInGroupResource::collection($shops)->toJson(), true);
        } else {
            return json_decode(DashboardShopSalesInOrganisationResource::collection($shops)->toJson(), true);
        }


    }

}
