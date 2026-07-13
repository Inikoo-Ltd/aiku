<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Agent\Json;

use App\Actions\OrgAction;
use App\Http\Resources\CRM\Livechat\ChatAgentUserResource;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetChatAgentUsers extends OrgAction
{
    public function handle(): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('employees.contact_name', $value)
                    ->orWhereStartWith('employees.alias', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(Employee::class)
            ->whereNotNull('employees.user_id')
            ->whereIn('employees.state', ['working', 'leaving'])
            ->leftJoin('organisations', 'organisations.id', '=', 'employees.organisation_id')
            ->defaultSort('employees.contact_name')
            ->select([
                'employees.user_id',
                'employees.contact_name',
                'employees.alias',
                'organisations.code as organisation_code',
            ]);

        return $queryBuilder->allowedSorts(['contact_name', 'alias'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null)
            ->withQueryString();
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle();
    }

    public function jsonResponse(LengthAwarePaginator $users): AnonymousResourceCollection
    {
        return ChatAgentUserResource::collection($users);
    }
}
