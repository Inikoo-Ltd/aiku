<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\UI;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\User\UI\ShowUser;
use App\Actions\SysAdmin\User\WithUserSubNavigation;
use App\Http\Resources\SysAdmin\UserRequestLogsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Analytics\UserRequest;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexUserRequests extends OrgAction
{
    use WithAnalyticsSubNavigations;
    use WithUserSubNavigation;

    private ?User $user = null;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('sysadmin.view');
    }

    public function handle(Group $group, ?User $user = null, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereRaw('users.username ILIKE ?', ["%$value%"])
                    ->orWhereRaw('user_requests.route_name ILIKE ?', ["%$value%"])
                    ->orWhereRaw('user_requests.ip_address ILIKE ?', ["%$value%"]);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(UserRequest::class)
            ->where('user_requests.group_id', $group->id)
            ->when($user, fn ($query) => $query->where('user_requests.user_id', $user->id))
            ->leftJoin('users', 'users.id', '=', 'user_requests.user_id')
            ->select([
                'user_requests.id',
                'users.username as username',
                'user_requests.ip_address as ip_address',
                'user_requests.route_name as route_name',
                'user_requests.route_params as arguments',
                'user_requests.date as datetime',
                'user_requests.location as location',
                'user_requests.device as device_type',
                'user_requests.os as platform',
                'user_requests.browser as browser',
            ])
            ->selectRaw("split_part(user_requests.route_name, '.', 2) as module")
            ->defaultSort('-datetime')
            ->allowedSorts(['username', 'route_name', 'module', 'ip_address', 'datetime'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
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
                ->withTitle(title: __('User requests'))
                ->withLabelRecord([__('request'), __('requests')])
                ->column(key: 'datetime', label: __('Date'), canBeHidden: false, sortable: true);

            if (!$this->user) {
                $table->column(key: 'username', label: __('User'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table
                ->column(key: 'route_name', label: __('Route'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'module', label: __('Module'), canBeHidden: false, sortable: true)
                ->column(key: 'ip_address', label: __('IP'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('Location'), canBeHidden: false)
                ->column(key: 'user_agent', label: __('Device'), canBeHidden: false)
                ->defaultSort('-datetime');
        };
    }

    public function htmlResponse(LengthAwarePaginator $userRequests, ActionRequest $request): Response
    {
        return Inertia::render(
            'SysAdmin/UserRequests',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('User requests'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-user-slash'],
                        'title' => __('User requests'),
                    ],
                    'title'         => __('User requests'),
                    'subNavigation' => $this->user
                        ? $this->getUserNavigation($this->user, $request)
                        : $this->getAnalyticsNavigation($this->group, $request),
                ],
                'data' => UserRequestLogsResource::collection($userRequests),
            ]
        )->table($this->tableStructure());
    }

    public function inUser(User $user, ActionRequest $request): LengthAwarePaginator
    {
        $this->user = $user;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group, $user);
    }

    public function getBreadcrumbs(): array
    {
        if ($this->user) {
            return array_merge(
                ShowUser::make()->getBreadcrumbs('grp.sysadmin.users.show', ['user' => $this->user->slug]),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.sysadmin.users.show.visit_logs.index',
                                'parameters' => ['user' => $this->user->slug],
                            ],
                            'label' => __('Visit logs'),
                            'icon'  => 'fal fa-bars',
                        ]
                    ]
                ]
            );
        }

        return array_merge(
            ShowSysAdminAnalyticsDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.sysadmin.analytics.request.index',
                        ],
                        'label' => __('User requests'),
                    ]
                ]
            ]
        );
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group);
    }
}
