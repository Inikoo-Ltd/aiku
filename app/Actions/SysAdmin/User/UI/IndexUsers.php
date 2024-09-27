<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\UI;

use App\Actions\GrpAction;
use App\Actions\Helpers\History\IndexHistory;
use App\Actions\SysAdmin\UserRequest\IndexUserRequestLogs;
use App\Actions\SysAdmin\WithSysAdminAuthorization;
use App\Actions\UI\Grp\SysAdmin\ShowSysAdminDashboard;
use App\Enums\UI\SysAdmin\UsersTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\SysAdmin\UserRequestLogsResource;
use App\Http\Resources\SysAdmin\UsersResource;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexUsers extends GrpAction
{
    use WithSysAdminAuthorization;


    protected function getElementGroups(Group $group): array
    {
        return
            [
                'status' => [
                    'label'    => __('Status'),
                    'elements' => [
                        'active'    =>
                            [
                                __('Active'),
                                $group->sysadminStats->number_users_status_active
                            ],
                        'suspended' => [
                            __('Suspended'),
                            $group->sysadminStats->number_users_status_inactive
                        ]
                    ],
                    'engine'   => function ($query, $elements) {
                        $query->where('status', array_pop($elements) === 'active');
                    }

                ],
            ];
    }

    public function handle(Group $group, $scope = 'active', $prefix = null): LengthAwarePaginator
    {
        $this->group  = $group;
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('contact_name', $value)
                    ->orWhereStartWith('users.username', $value);
            });
        });


        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $queryBuilder = QueryBuilder::for(User::class)
            ->whereNotNull('type');

        if ($scope == 'active') {
            $queryBuilder->where('status', true);
        } elseif ($scope == 'suspended') {
            $queryBuilder->where('status', false);
        }

        if ($scope == 'all') {
            foreach ($this->getElementGroups($group) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }

        return $queryBuilder->with('parent')
            ->defaultSort('username')
            ->select(['username', 'parent_type', 'parent_id', 'email', 'contact_name', 'image_id', 'status'])
            ->allowedSorts(['username', 'email', 'parent_type', 'contact_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function tableStructure(Group $group, string $scope = 'active', ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $group, $scope) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            if ($scope == 'all') {
                foreach ($this->getElementGroups($group) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }

            $table
                ->withTitle(title: __('Users'))
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->column(key: 'status', label: ['data' => ['fal', 'fa-yin-yang'], 'type' => 'icon', 'tooltip' => __('status')], type: 'icon')
                ->column(key: 'image', label: ['data' => ['fal', 'fa-user-circle'], 'type' => 'icon', 'tooltip' => __('avatar')], type: 'avatar')
                ->column(key: 'username', label: __('username'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'contact_name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'parent_type', label: __('type'), canBeHidden: false, sortable: true)
                ->defaultSort('username');
        };
    }

    public function jsonResponse(LengthAwarePaginator $users): AnonymousResourceCollection
    {
        return UsersResource::collection($users);
    }

    public function htmlResponse(LengthAwarePaginator $users, ActionRequest $request): Response
    {
        return Inertia::render(
            'SysAdmin/Users',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName()),
                'title'       => __('users'),

                'labels' => [
                    'usernameNoSet' => __('username no set')
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => UsersTabsEnum::navigation(),
                ],

                UsersTabsEnum::ACTIVE_USERS->value => $this->tab == UsersTabsEnum::ACTIVE_USERS->value ?
                    fn () => UsersResource::collection($users)
                    : Inertia::lazy(fn () => UsersResource::collection($users)),

                UsersTabsEnum::SUSPENDED_USERS->value => $this->tab == UsersTabsEnum::SUSPENDED_USERS->value ?
                    fn () => UsersResource::collection(IndexUsers::run($this->group, 'suspended'))
                    : Inertia::lazy(fn () => UsersResource::collection(IndexUsers::run($this->group, 'suspended'))),

                UsersTabsEnum::USERS_REQUESTS->value => $this->tab == UsersTabsEnum::USERS_REQUESTS->value ?
                    fn () => UserRequestLogsResource::collection(IndexUserRequestLogs::run())
                    : Inertia::lazy(fn () => UserRequestLogsResource::collection(IndexUserRequestLogs::run())),

                UsersTabsEnum::USERS->value => $this->tab == UsersTabsEnum::USERS->value ?
                    fn () => UsersResource::collection(IndexUsers::run($this->group, 'all'))
                    : Inertia::lazy(fn () => UsersResource::collection(IndexUsers::run($this->group, 'all'))),

                UsersTabsEnum::USERS_HISTORIES->value => $this->tab == UsersTabsEnum::USERS_HISTORIES->value ?
                    fn () => HistoryResource::collection(IndexHistory::run(User::class))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run(User::class)))

            ]
        )->table(
            $this->tableStructure(
                group: $this->group,
                scope: 'all',
                prefix: UsersTabsEnum::USERS->value
            )
        )->table(
            $this->tableStructure(
                group: $this->group,
                prefix: UsersTabsEnum::ACTIVE_USERS->value
            )
        )->table(
            $this->tableStructure(
                group: $this->group,
                scope: 'suspended',
                prefix: UsersTabsEnum::SUSPENDED_USERS->value
            )
        )
            ->table(IndexUserRequestLogs::make()->tableStructure())
            ->table(
                IndexHistory::make()->tableStructure(
                    prefix: UsersTabsEnum::USERS_HISTORIES->value
                )
            );
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation(app('group'), $request)->withTab(UsersTabsEnum::values());

        return $this->handle(group: $this->group, prefix: 'users');
    }

    public function getBreadcrumbs(string $routeName): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Users'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.sysadmin.users.index' =>
            array_merge(
                ShowSysAdminDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => 'grp.sysadmin.users.index',
                        null
                    ]
                ),
            ),


            default => []
        };
    }

}
