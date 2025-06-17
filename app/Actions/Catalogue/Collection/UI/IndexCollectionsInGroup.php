<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:05:41 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\Inventory\WithGroupOverviewAuthorisation;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexCollectionsInGroup extends OrgAction
{
    use WithGroupOverviewAuthorisation;

    public function handle(Group $group, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Collection::class);
        $queryBuilder->where('collections.group_id', $group->id);
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
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'organisations.code as organisation_code',
            ]);


        return $queryBuilder
            ->allowedSorts(['organisation_code', 'shop_code', 'code', 'name', 'number_families', 'number_products'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group $group, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($group, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __('No collections found'),
                        'count' => $group->catalogueStats->number_collections
                    ]
                );

            $table
                ->column(key: 'organisation_code', label: __('org'), canBeHidden: false, sortable: true, searchable: true)
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
            'title' => $title
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
                    'afterTitle' => [
                        'label' => '@ '.__('group')
                    ],
                    'actions'    => [],
                ],
                'routes'      => null,
                'data'        => CollectionsResource::collection($collections),
            ]
        )->table($this->tableStructure($this->group));
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group);
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
            ShowGroupOverviewHub::make()->getBreadcrumbs(),
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
