<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 3 Aug 2026 23:18:24 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\WebUserRequest\UI\Traits;

use App\Http\Resources\CRM\WebUserRequestsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Analytics\WebUserRequest;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

trait WithWebUserRequestsUI
{
    public function getWebUserRequestsQueryBuilder($prefix = null): QueryBuilder
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('web_users.username', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = QueryBuilder::for(WebUserRequest::class);

        return $queryBuilder
            ->leftJoin('web_users', 'web_users.id', '=', 'web_user_requests.web_user_id')
            ->defaultSort('web_users.username')
            ->select([
                'web_users.username',
                'web_users.id',
                'web_user_requests.*',
            ])
            ->allowedSorts(['username', 'ip_address', 'date'])
            ->allowedFilters([$globalSearch]);
    }

    public function finalizeWebUserRequestsQuery(QueryBuilder $queryBuilder, $prefix = null): LengthAwarePaginator
    {
        return $queryBuilder
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
                ->column(key: 'username', label: __('Username'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'ip_address', label: __('ip address'), canBeHidden: false, sortable: true)
                ->column(key: 'url', label: __('url'), canBeHidden: false)
                ->column(key: 'user_agent', label: __('user agent'), canBeHidden: false)
                ->column(key: 'location', label: __('location'), canBeHidden: false)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true)
                ->defaultSort('-date');
        };
    }

    public function htmlResponse(LengthAwarePaginator $requests, ActionRequest $request): Response
    {
        $title = __('Web User Requests');

        return Inertia::render(
            'Org/Web/WebUserRequests',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title' => $title,
                'pageHead' => [
                    'title' => $title,
                ],
                'data' => WebUserRequestsResource::collection($requests),
            ]
        )->table($this->tableStructure());
    }
}
