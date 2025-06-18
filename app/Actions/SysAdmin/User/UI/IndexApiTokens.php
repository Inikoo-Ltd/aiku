<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\SysAdmin\User\UI;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\User\WithUsersSubNavigation;
use App\Actions\SysAdmin\WithSysAdminAuthorization;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\QueryBuilder\AllowedFilter;

class IndexApiTokens extends OrgAction
{
    use WithSysAdminAuthorization;
    use WithUsersSubNavigation;

    private User $user;

    public function handle(User $user, $prefix = null): LengthAwarePaginator
    {

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('name', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(PersonalAccessToken::class);
        $queryBuilder->where('tokenable_type', class_basename($user))
                    ->where('tokenable_id', $user->id);

        return $queryBuilder
            ->defaultSort('created_at')
            ->select(['id', 'name', 'abilities', 'last_used_at', 'created_at'])
            ->allowedSorts(['name', 'created_at', 'last_used_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(User $user, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($user->organisation, $request);
        return $this->handle($user);
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->column(key: 'name', label: __('Token Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'abilities', label: __('Abilities'), canBeHidden: true)
                ->column(key: 'last_used_at', label: __('Last Used'), canBeHidden: false, sortable: true)
                ->column(key: 'created_at', label: __('Created At'), canBeHidden: false, sortable: true)
                ->defaultSort('created_at');
        };
    }
}
