<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Jun 2025 23:49:07 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Analytics\WebUserRequest\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowOrganisationOverviewHub;
use App\Actions\Traits\Authorisations\Inventory\WithOrganisationOverviewAuthorisation;
use App\Http\Resources\CRM\WebUserRequestsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Analytics\WebUserRequest;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWebUserRequestsInOrganisation extends OrgAction
{
    use WithOrganisationOverviewAuthorisation;


    public function handle(Organisation $organisation, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('web_users.username', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }
        $queryBuilder = QueryBuilder::for(WebUserRequest::class);
        $queryBuilder->where('web_user_requests.organisation_id', $organisation->id);
        $queryBuilder->leftJoin('web_users', 'web_users.id', '=', 'web_user_requests.web_user_id');


        return $queryBuilder
            ->defaultSort('web_users.username')
            ->select([
                'web_users.username',
                'web_users.id',
                'web_user_requests.*'
            ])
            ->allowedSorts(['username', 'ip_address', 'date'])
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
                ->column(key: 'username', label: __('username'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'ip_address', label: __('ip address'), canBeHidden: false, sortable: true)
                ->column(key: 'url', label: __('url'), canBeHidden: false)
                ->column(key: 'user_agent', label: __('user agent'), canBeHidden: false)
                ->column(key: 'location', label: __('location'), canBeHidden: false)
                ->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true)
                ->defaultSort('-date');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(LengthAwarePaginator $requests, ActionRequest $request): Response
    {

        $title   = __('Web User Requests');

        return Inertia::render(
            'Org/Web/WebUserRequests',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                ],
                'data'        => WebUserRequestsResource::collection($requests),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowOrganisationOverviewHub::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.overview.web_user_requests.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Web User Requests'),
                    ]
                ]
            ]
        );
    }


}
