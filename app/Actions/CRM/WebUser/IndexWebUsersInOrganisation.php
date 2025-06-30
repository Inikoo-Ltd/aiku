<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Jun 2025 23:27:54 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowOrganisationOverviewHub;
use App\Actions\Traits\Authorisations\Inventory\WithOrganisationOverviewAuthorisation;
use App\Http\Resources\CRM\WebUsersResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWebUsersInOrganisation extends OrgAction
{
    use WithOrganisationOverviewAuthorisation;

    public function handle(Organisation $organisation, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('username', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(WebUser::class);
        $queryBuilder->leftJoin('shops', 'web_users.shop_id', '=', 'shops.id');
        $queryBuilder->where('web_users.organisation_id', $organisation->id);


        return $queryBuilder
            ->defaultSort('username')
            ->select([
                'web_users.username',
                'web_users.id',
                'web_users.email',
                'web_users.slug',
                'web_users.created_at',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'shops.name as shop_name'
            ])
            ->allowedSorts(['email', 'username', 'created_at', 'shop_code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }



    public function htmlResponse(LengthAwarePaginator $webUsers, ActionRequest $request): Response
    {


        $icon       = ['fal', 'fa-terminal'];
        $title      = __('web users');
        $afterTitle = null;
        $iconRight  = null;


        return Inertia::render(
            'Org/Shop/CRM/WebUsers',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'       => __('web users'),
                'pageHead'    => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                ],

                'data'        => WebUsersResource::collection($webUsers),

            ]
        )->table($this->tableStructure());
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
                ->withEmptyState();
            $table
                ->column(key: 'shop_code', label: __('shop'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'username', label: __('username'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'created_at', label: __('Created at'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('username');
        };
    }


    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle(organisation:$organisation);
    }




    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Web users'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return array_merge(
            ShowOrganisationOverviewHub::make()->getBreadcrumbs($routeParameters),
            $headCrumb(
                [
                    'name'       => $routeName,
                    'parameters' => $routeParameters
                ],
            )
        );
    }
}
