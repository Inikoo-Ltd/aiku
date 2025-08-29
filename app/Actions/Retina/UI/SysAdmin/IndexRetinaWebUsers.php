<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 Jan 2025 21:36:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\SysAdmin;

use App\Actions\RetinaAction;
use App\Http\Resources\CRM\WebUsersRetinaResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\WebUser;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaWebUsers extends RetinaAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle();
    }


    public function handle($prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('web_users.contact_name', $value)
                    ->orWhereStartWith('web_users.username', $value);
            });
        });


        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $queryBuilder = QueryBuilder::for(WebUser::class);
        $queryBuilder->leftJoin('web_user_stats', 'web_user_stats.web_user_id', '=', 'web_users.id');
        $queryBuilder->where('customer_id', $this->customer->id);


        return  $queryBuilder
            ->defaultSort('username')
            ->select(['web_users.slug','web_users.id', 'web_user_stats.last_device', 'web_user_stats.last_location', 'web_user_stats.last_os' ,'web_users.username', 'web_users.image_id','web_users.contact_name', 'web_users.status', 'web_users.is_root', 'web_user_stats.last_active_at as last_active'])
            ->allowedSorts(['web_users.status', 'username', 'contact_name', 'last_active'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withTitle(title: __('Users'))
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->column(key: 'image', label: ['data' => ['fal', 'fa-user-circle'], 'type' => 'icon', 'tooltip' => __('avatar')], type: 'avatar')
                ->column(key: 'status', label: ['data' => ['fal', 'fa-yin-yang'], 'type' => 'icon', 'tooltip' => __('status')], type: 'icon')
                ->column(key: 'username', label: __('username'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'contact_name', label: __('contact name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'last_location', label: __('location'), canBeHidden: false)
                ->column(key: 'last_device', label: __('device'), canBeHidden: false)
                ->column(key: 'last_active', label: __('last active'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('username');
        };
    }

    public function jsonResponse(LengthAwarePaginator $users): AnonymousResourceCollection
    {
        return WebUsersRetinaResource::collection($users);
    }

    public function htmlResponse(LengthAwarePaginator $webUsers, ActionRequest $request): Response
    {
        $title = __('Users');

        return Inertia::render(
            'SysAdmin/RetinaWebUsers',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName()),
                'title'       => $title,
                'pageHead'    => [
                    'title'   => $title,
                    'icon'    => [
                        'type' => 'icon',
                        'icon' => 'fal fa-user-circle'
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('user'),
                            'route' => [
                                'name'       => preg_replace('/index$/', 'create', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ]
                    ]
                ],

                'labels' => [
                    'usernameNoSet' => __('username no set')
                ],

                'data' => WebUsersRetinaResource::collection($webUsers),
            ]
        )->table(
            $this->tableStructure()
        );
    }


    public function getBreadcrumbs(string $routeName): array
    {
        return match ($routeName) {
            'retina.sysadmin.web-users.index',
            'retina.sysadmin.web-users.show' =>
            array_merge(
                ShowRetinaFulfilmentSysAdminDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.sysadmin.web-users.index',
                            ],
                            'label' => __('Users'),
                            'icon'  => 'fal fa-bars',
                        ],

                    ]
                ]
            ),
        };
    }

}
