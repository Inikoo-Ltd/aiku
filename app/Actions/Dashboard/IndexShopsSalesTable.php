<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Mar 2025 10:57:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dashboard;

use App\Actions\OrgAction;
use App\Http\Resources\Dashboards\DashboardShopSalesResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;

class IndexShopsSalesTable extends OrgAction
{
    public function handle(Group|Organisation $parent)
    {

        $queryBuilder = QueryBuilder::for(Shop::class);
        if (class_basename($parent) == 'Organisation') {
            $queryBuilder->where('organisation_id', $parent->id);
        } else {
            $queryBuilder->where('group_id', $parent->id);
        }



        return $queryBuilder
            ->defaultSort('shops.code')
            ->select(['code', 'id', 'name', 'slug', 'type', 'state','shops.currency_id','shops.organisation_id'])
            ->allowedSorts(['code', 'name', 'type', 'state'])
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

        return json_decode(DashboardShopSalesResource::collection($shops)->toJson(), true);
    }

}
