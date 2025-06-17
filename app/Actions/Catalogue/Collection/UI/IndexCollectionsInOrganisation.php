<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Jun 2025 22:20:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowOrganisationOverviewHub;
use App\Actions\Traits\Authorisations\Inventory\WithOrganisationOverviewAuthorisation;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexCollectionsInOrganisation extends OrgAction
{
    use WithOrganisationOverviewAuthorisation;


    public function handle(Organisation $organisation, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Collection::class);
        $queryBuilder->where('collections.organisation_id', $organisation->id);
        $queryBuilder->leftjoin('collection_stats', 'collections.id', 'collection_stats.collection_id');


        $queryBuilder
            ->leftJoin('organisations', 'collections.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'collections.shop_id', '=', 'shops.id');
        $queryBuilder
            ->defaultSort('collections.code')
            ->select([
                'collections.id',
                'collections.code',
                'collections.state',
                'collections.name',
                'collections.description',
                'collections.created_at',
                'collections.updated_at',
                'collections.slug',
                'collection_stats.number_families',
                'collection_stats.number_products',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'organisations.slug as organisation_slug',
                'organisations.code as organisation_code',
                'organisations.name as organisation_name',
            ]);


        return $queryBuilder
            ->allowedSorts(['shop_code', 'code', 'name', 'number_families', 'number_products'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation $organisation, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($organisation, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }


            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __('No collections found'),
                        'count' => $organisation->catalogueStats->number_collections
                    ]
                );

            $table
                ->column(key: 'shop_code', label: __('shop'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'state_icon', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_families', label: __('Families'), canBeHidden: false)
                ->column(key: 'number_products', label: __('Products'), canBeHidden: false);
        };
    }

    public function jsonResponse(LengthAwarePaginator $collections): AnonymousResourceCollection
    {
        return CollectionsResource::collection($collections);
    }

    public function htmlResponse(LengthAwarePaginator $collections, ActionRequest $request): Response
    {
        $title = __('Collections');
        $icon  = [
            'icon'  => ['fal', 'fa-album-collection'],
            'title' => __('collections')
        ];


        return Inertia::render(
            'Org/Catalogue/Collections',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title'      => $title,
                    'icon'       => $icon,
                    'afterTitle'    => [
                        'label'     => '@ '.__('organisation').' '.$this->organisation->code,
                    ],
                    'actions'    => [],
                ],
                'routes'      => null,
                'data'        => CollectionsResource::collection($collections),

            ]
        )->table($this->tableStructure($this->organisation));
    }


    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle(organisation: $organisation);
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Collections'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return array_merge(
            ShowOrganisationOverviewHub::make()->getBreadcrumbs($routeParameters),
            $headCrumb(
                [
                    'name'       => $routeName,
                    'parameters' => $routeParameters
                ],
                $suffix
            )
        );
    }
}
