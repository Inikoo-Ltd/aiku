<?php

/*
 * author Arya Permana - Kirin
 * created on 10-04-2025-10h-30m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\SysAdmin\User;

use App\Actions\OrgAction;
use App\Http\Resources\SysAdmin\SupervisorUsersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Role;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetSupervisorUsers extends OrgAction
{
    public function inFulfilment(Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }


    public function handle(Fulfilment $scope, $prefix = null): LengthAwarePaginator
    {

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('users.username', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        if ($scope instanceof Fulfilment) {
            $role = Role::where('name', "fulfilment-shop-supervisor-".$scope->id)->first();
        }

        $queryBuilder = QueryBuilder::for(User::class);

        $queryBuilder->leftJoin('model_has_roles', function ($join) {
            $join->on('users.id', '=', 'model_has_roles.model_id')
                ->where('model_has_roles.model_type', '=', 'User');
        });
        $queryBuilder->where('model_has_roles.role_id', $role->id);

        return $queryBuilder
            ->defaultSort('users.username')
            ->allowedSorts(['username', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $users): AnonymousResourceCollection
    {
        return SupervisorUsersResource::collection($users);
    }
}
