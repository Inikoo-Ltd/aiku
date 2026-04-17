<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Nov 2024 10:25:17 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Analytics\UserRequest\UI;

use App\Actions\Analytics\UserRequest\Traits\WithFormattedRequestLogs;
use App\Actions\Elasticsearch\BuildElasticsearchClient;
use App\Actions\GrpAction;
use App\Actions\SysAdmin\UI\ShowSysAdminDashboard;
use App\Actions\SysAdmin\UI\WithAnalyticsSubNavigations;
use App\Actions\SysAdmin\User\WithUsersSubNavigation;
use App\Enums\Elasticsearch\ElasticsearchUserRequestTypeEnum;
use App\Http\Resources\SysAdmin\UserRequestLogsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Analytics\UserRequest;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\QueryBuilder\AllowedFilter;

// review this
class IndexUserRequestLogs extends GrpAction
{
    use AsObject;
    use WithFormattedRequestLogs;
    use WithUsersSubNavigation;
    use WithAnalyticsSubNavigations; //TODO: For analytics dashboard

    public function handle($prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('users.contact_name', $value)
                    ->orWhereStartWith('users.username', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(UserRequest::class);
        $queryBuilder->leftJoin('users', 'users.id', '=', 'user_requests.user_id')
            ->leftJoin('groups', 'groups.id', '=', 'user_requests.group_id')
            ->leftJoin('aiku_scoped_sections', 'aiku_scoped_sections.id', '=', 'user_requests.aiku_scoped_section_id');

        return $queryBuilder
            ->defaultSort('username')
            ->select([
                'users.username',
                'users.email',
                'users.slug',
                'users.contact_name',
                'users.image_id',
                'users.status',
                'groups.name as group_name',
                'aiku_scoped_sections.name as section_name',
                'user_requests.date',
                'user_requests.route_name',
                'user_requests.route_params',
                'user_requests.os',
                'user_requests.device',
                'user_requests.browser',
                'user_requests.ip_address',
                'user_requests.location'
            ])
            ->allowedSorts([
                'username',
                'email',
                'contact_name',
                'is_two_factor_required',
                'group_name',
                'section_name',
                'date',
                'os',
                'device',
                'browser',
                'ip_address'
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function htmlResponse(LengthAwarePaginator $requests, ActionRequest $request): Response
    {
        $subNavigation = $this->getUsersNavigation($this->group, $request);
        $title = __('User Requests');
        return Inertia::render(
            'SysAdmin/UserRequests',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title'   => $title,
                    'subNavigation' => $subNavigation,
                ],
                'data'        => UserRequestLogsResource::collection($requests),
            ]
        )->table($this->tableStructure());
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->name('vst')
                ->column(key: 'username', label: __('Username'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'email', label: __('Email'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'contact_name', label: __('Contact Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'group_name', label: __('Group Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'section_name', label: __('Section Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'os', label: __('OS'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'device', label: __('Device'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'browser', label: __('Browser'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'ip_address', label: __('IP Address'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $group = group();
        $this->initialisation($group, $request);
        return $this->handle();
    }

    public function getBreadcrumbs($suffix = null): array
    {
        return array_merge(
            ShowSysAdminDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.sysadmin.analytics.request.index',
                        ],
                        'label' => __('User Requests'),
                        'icon'  => 'fal fa-bars',
                    ],
                    'suffix' => $suffix

                ]
            ]
        );
    }
}
