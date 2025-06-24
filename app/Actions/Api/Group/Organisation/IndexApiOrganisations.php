<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Jun 2025 23:41:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Api\Group\Organisation;

use App\Actions\OrgAction;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Http\Resources\SysAdmin\Organisation\OrganisationsApiResource;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexApiOrganisations extends OrgAction
{
    public function handle(Group $group): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('name', $value)->orWhereStartWith('code', $value);
            });
        });



        $queryBuilder = QueryBuilder::for(Organisation::class);
        $queryBuilder->where('group_id', $group->id);
        $queryBuilder->where('type', '!=', OrganisationTypeEnum::AGENT);
        $queryBuilder->leftJoin('organisation_human_resources_stats', 'organisations.id', '=', 'organisation_human_resources_stats.organisation_id');
        $queryBuilder->leftJoin('organisation_catalogue_stats', 'organisations.id', '=', 'organisation_catalogue_stats.organisation_id');


        return $queryBuilder
            ->defaultSort('code')
            ->select([
                'organisations.id',
                'name',
                'slug',
                'type',
                'code',
            ])
            ->allowedSorts([
                'id',
                'name',
                'type',
                'code',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function jsonResponse(LengthAwarePaginator $organisations): AnonymousResourceCollection
    {
        return OrganisationsApiResource::collection($organisations);
    }


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);
        return $this->handle($this->group);
    }


}
