<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Aug 2025 16:49:11 Central Standard Time, Mexico-Tokio
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dashboard;

use App\Actions\OrgAction;
use App\Http\Resources\Dashboards\DashboardMasterShopSalesInGroupResource;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;

class IndexMasterShopsSalesTable extends OrgAction
{
    public function handle(Group $group)
    {
        $queryBuilder = QueryBuilder::for(MasterShop::class);
        $queryBuilder->leftJoin('master_shop_sales_intervals', 'master_shops.id', 'master_shop_sales_intervals.master_shop_id');
        $queryBuilder->leftJoin('master_shop_ordering_intervals', 'master_shops.id', 'master_shop_ordering_intervals.master_shop_id');


        $queryBuilder->where('master_shops.group_id', $group->id);


        $queryBuilder
            ->defaultSort('master_shops.code')
            ->select([
                'master_shops.id',
                'master_shops.code',
                'master_shops.name',
                'master_shops.slug',
                'master_shops.type',
                'master_shops.status',
                'master_shop_sales_intervals.*',
                'master_shop_ordering_intervals.*',
            ]);
        $queryBuilder->selectRaw('\''.$group->currency->code.'\' as group_currency_code');


        return $queryBuilder->allowedSorts(['code', 'name', 'type', 'state'])
            ->withPaginator(null)
            ->withQueryString();
    }


    public function action(Group|Organisation $parent): array
    {
        $this->initialisationFromGroup($parent, []);
        $shops = $this->handle($parent);

        return json_decode(DashboardMasterShopSalesInGroupResource::collection($shops)->toJson(), true);
    }

}
