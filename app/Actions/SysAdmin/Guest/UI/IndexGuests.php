<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Guest\UI;

use App\Actions\GrpAction;
use App\Actions\SysAdmin\Guest\WithGuestsSubNavigations;
use App\Actions\SysAdmin\UI\ShowSysAdminDashboard;
use App\Actions\SysAdmin\WithSysAdminAuthorization;
use App\Http\Resources\SysAdmin\GuestsResource;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Guest;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexGuests extends GrpAction
{
    use WithSysAdminAuthorization;
    use WithGuestsSubNavigations;

    private string $scope;

    protected function getElementGroups(): array
    {
        return [
            'status' => [
                'label'    => __('Status'),
                'elements' => ['active' => __('Active'), 'inactive' => __('Inactive')],
                'engine'   => function ($query, $elements) {
                    $query->where('users.status', array_pop($elements) === 'active');
                }

            ]
        ];
    }


    public function handle(Group $group, string $scope = 'active', $prefix = null): LengthAwarePaginator
    {
        $this->group  = $group;
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('guests.contact_name', $value)
                    ->orWhereStartWith('guests.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Guest::class)
            ->leftJoin(
                'user_has_models',
                function ($leftJoin) {
                    $leftJoin
                        ->on('user_has_models.model_id', '=', 'guests.id')
                        ->where('user_has_models.model_type', '=', 'Guest');
                }
            )
            ->leftJoin('user_stats', 'user_stats.user_id', 'user_has_models.user_id');
        if ($scope == 'active') {
            $queryBuilder->where('guests.status', true);
        } elseif ($scope == 'inactive') {
            $queryBuilder->where('guests.status', false);
        } else {
            foreach ($this->getElementGroups() as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }

        return $queryBuilder
            ->defaultSort('guests.code')
            ->select(['guests.id', 'guests.slug', 'guests.code', 'guests.contact_name', 'guests.email', 'number_logins', 'last_login_at', 'number_failed_logins', 'last_failed_login_at'])
            ->allowedSorts(['code', 'contact_name', 'email', 'number_logins', 'last_login_at', 'number_failed_logins', 'last_failed_login_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group $group, string $scope = 'active', $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $group, $scope) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            if ($scope == 'all') {
                foreach ($this->getElementGroups() as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }
            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __('no guest'),
                        'description' => $this->canEdit ? __('Get started by creating a new guest.') : null,
                        'count'       => $group->sysadminStats->number_guests_status_active,
                        'action'      => $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('New guest'),
                            'label'   => __('guest'),
                            'route'   => [
                                'name'       => 'grp.sysadmin.guests.create',
                                'parameters' => []
                            ]
                        ] : null
                    ]
                )
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'contact_name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'email', label: __('email'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('slug');
        };
    }


    public function jsonResponse(LengthAwarePaginator $guests): AnonymousResourceCollection
    {
        return GuestsResource::collection($guests);
    }


    public function htmlResponse(LengthAwarePaginator $guests, ActionRequest $request): Response
    {
        $subNavigation = $this->getGuestsNavigation($this->group, $request);
        $title = __('Active');
        $icon  = [
            'icon'  => ['fal', 'fa-user-alien'],
            'title' => __('Active')
        ];
        if ($this->scope == 'inactive') {
            $title = __('Inactive');
            $icon  = [
                'icon'  => ['fal', 'fa-user-slash'],
                'title' => __('Inactive guests')
            ];
        } elseif ($this->scope == 'all') {
            $title = __('All');
            $icon  = [
                'icon'  => ['fal', 'fa-users'],
                'title' => __('All guests')
            ];
        }
        return Inertia::render(
            'SysAdmin/Guests',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName()),
                'title'       => __('guests'),
                'pageHead'    => [
                    'title'         => $title,
                    'model'         => __('guests'),
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions' => [
                        $this->canEdit && $request->route()->getName() == 'grp.sysadmin.guests.index' ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('guest'),
                            'route' => [

                                'name'       => 'grp.sysadmin.guests.create',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]

                        ] : false
                    ]
                ],
                'data'        => GuestsResource::collection($guests),
            ]
        )->table($this->tableStructure(group:$this->group, scope:$this->scope));
    }

    public function inInactive(ActionRequest $request): LengthAwarePaginator
    {
        $group = group();
        $this->scope = 'inactive';
        $this->initialisation($group, $request);

        return $this->handle($group, $this->scope);
    }

    public function inActive(ActionRequest $request): LengthAwarePaginator
    {
        $group = group();
        $this->scope = 'active';
        $this->initialisation($group, $request);

        return $this->handle($group, $this->scope);
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $group = group();
        $this->scope = 'all';
        $this->initialisation($group, $request);

        return $this->handle($group, $this->scope);
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
            'grp.sysadmin.guests.index' =>
            array_merge(
                ShowSysAdminDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => 'grp.sysadmin.users.index',
                        null
                    ]
                ),
            ),
            'grp.sysadmin.guests.inactive.index' =>
            array_merge(
                ShowSysAdminDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => 'grp.sysadmin.guests.inactive.index',
                        null
                    ]
                ),
            ),
            'grp.sysadmin.guests.all.index' =>
            array_merge(
                ShowSysAdminDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => 'grp.sysadmin.guests.all.index',
                        null
                    ]
                ),
            ),


            default => []
        };
    }


}
