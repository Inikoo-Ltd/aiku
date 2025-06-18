<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\UI;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\UI\ShowSysAdminDashboard;
use App\Actions\SysAdmin\User\WithUsersSubNavigation;
use App\Actions\SysAdmin\WithSysAdminAuthorization;
use App\Http\Resources\SysAdmin\ApiTokensResource;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
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

    public function htmlResponse(LengthAwarePaginator $tokens, ActionRequest $request): Response
    {
        return Inertia::render(
            'SysAdmin/ApiTokens',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), ['user' => $this->user->id]),
                'title'       => __('API Tokens'),
                'pageHead'    => [
                    'title' => __('API Tokens for :username', ['username' => $this->user->username]),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-key'],
                        'title' => __('API Tokens')
                    ],
                ],
                'data' => ApiTokensResource::collection($tokens),
                'user' => $this->user->only(['id', 'username', 'contact_name']),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowSysAdminDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.sysadmin.users.index',
                            'parameters' => []
                        ],
                        'label' => __('Users'),
                        'icon'  => 'fal fa-users'
                    ],
                ],
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.sysadmin.users.show',
                            'parameters' => ['user' => $routeParameters['user']]
                        ],
                        'label' => $this->user->username,
                        'icon'  => 'fal fa-user'
                    ],
                ],
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.sysadmin.users.tokens.index',
                            'parameters' => ['user' => $routeParameters['user']]
                        ],
                        'label' => __('API Tokens'),
                        'icon'  => 'fal fa-key'
                    ],
                ]
            ]
        );
    }
}
