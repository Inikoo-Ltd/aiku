<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Mar 2025 14:51:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dashboard;

use App\Actions\OrgAction;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Http\Resources\Dashboards\DashboardOrganisationSalesResource;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;

class IndexOrganisationsSalesTable extends OrgAction
{
    public function handle(Group $group)
    {
        $queryBuilder = QueryBuilder::for(Organisation::class);
        $queryBuilder->where('group_id', $group->id)->where('type', OrganisationTypeEnum::SHOP->value);




        return $queryBuilder
            ->defaultSort('organisations.code')
            ->select(['code', 'id', 'name', 'slug', 'type',  'organisations.currency_id'])
            ->allowedSorts(['code', 'name', 'type'])
            ->withPaginator(null)
            ->withQueryString();
    }


    public function action(Group $group): array
    {
        $this->initialisationFromGroup($group, []);
        $shops = $this->handle($group);

        return json_decode(DashboardOrganisationSalesResource::collection($shops)->toJson(), true);
    }

}
