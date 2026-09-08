<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\JobPosition\UI;

use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Role;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;

class IndexJobPositionRoles
{
    use AsAction;

    /**
     * Roles are scoped to one of a handful of unrelated models, so the scope name is resolved
     * in the query instead of hydrating a morph relation per row.
     */
    private const SCOPE_NAME_SQL = "
        case roles.scope_type
            when 'Group' then (select groups.name from groups where groups.id = roles.scope_id)
            when 'Organisation' then (select organisations.name from organisations where organisations.id = roles.scope_id)
            when 'Shop' then (select shops.name from shops where shops.id = roles.scope_id)
            when 'Warehouse' then (select warehouses.name from warehouses where warehouses.id = roles.scope_id)
            when 'Production' then (select productions.name from productions where productions.id = roles.scope_id)
            when 'Fulfilment' then (select fulfilments.slug from fulfilments where fulfilments.id = roles.scope_id)
        end
    ";

    public function handle(JobPosition $jobPosition, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('roles.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Role::class);
        $queryBuilder->join('job_position_role', 'job_position_role.role_id', '=', 'roles.id');
        $queryBuilder->where('job_position_role.job_position_id', $jobPosition->id);

        $queryBuilder->select(['roles.id', 'roles.name', 'roles.scope_type', 'roles.scope_id']);
        $queryBuilder->selectRaw('('.self::SCOPE_NAME_SQL.') as scope_name');
        $queryBuilder->selectRaw('(select count(*) from model_has_roles where model_has_roles.role_id = roles.id) as number_users');
        $queryBuilder->selectRaw('(select count(*) from role_has_permissions where role_has_permissions.role_id = roles.id) as number_permissions');

        return $queryBuilder
            ->defaultSort('name')
            ->allowedSorts(['name', 'scope_type', 'scope_name', 'number_users', 'number_permissions'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(JobPosition $jobPosition, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($jobPosition, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->withLabelRecord([__('role'), __('roles')]);
            $table->withEmptyState(
                [
                    'title'       => __('No system roles granted by this responsibility'),
                    'description' => __('Employees holding it get no extra permissions.'),
                    'count'       => $jobPosition->roles()->count(),
                ]
            );

            $table
                ->withGlobalSearch()
                ->column(key: 'label', label: __('Role'), canBeHidden: false, searchable: true)
                ->column(key: 'scope_type', label: __('Scope'), canBeHidden: false, sortable: true)
                ->column(key: 'scope_name', label: __('Scope name'), canBeHidden: false, sortable: true)
                ->column(key: 'number_users', label: __('Users'), canBeHidden: false, sortable: true)
                ->column(key: 'number_permissions', label: __('Permissions'), canBeHidden: true, sortable: true)
                ->defaultSort('name');
        };
    }
}
