<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:05:41 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterCollection\UI\ShowMasterCollection;
use App\Actions\Masters\MasterCollection\UI\WithMasterCollectionNavigation;
use App\Actions\Masters\MasterCollection\UI\WithMasterCollectionSubNavigation;
use App\Enums\UI\Catalogue\CollectionsTabsEnum;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCollectionsInMasterCollection extends GrpAction
{
    use WithMasterCollectionNavigation;
    use WithMasterCollectionSubNavigation;

    private MasterCollection $parent;

    public function handle(MasterCollection $masterCollection, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('collections.name', $value)
                    ->orWhereStartWith('collections.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(Collection::class);
        $queryBuilder->where('collections.master_collection_id', $masterCollection->id);
        $queryBuilder->leftjoin('collection_stats', 'collections.id', 'collection_stats.collection_id');


        $queryBuilder
            ->leftJoin('webpages', function ($join) {
                $join->on('collections.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'Collection')
                    ->whereNull('webpages.deleted_at');
            });

        $queryBuilder
            ->leftJoin('organisations', 'collections.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'collections.shop_id', '=', 'shops.id')
            ->leftJoin('websites', 'websites.shop_id', '=', 'shops.id');
        $queryBuilder
            ->defaultSort('collections.code')
            ->select([
                'collections.id',
                'collections.code',
                'collections.state',
                'collections.products_status',
                'collections.name',
                'collections.description',
                'collections.created_at',
                'collections.updated_at',
                'collections.slug',
                'collections.web_images',
                'collection_stats.number_families',
                'collection_stats.number_products',
                'collection_stats.number_parents',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'webpages.id as webpage_id',
                'webpages.state as webpage_state',
                'webpages.url as webpage_url',
                'webpages.slug as webpage_slug',
                'websites.slug as website_slug',
            ])
            ->selectRaw(
                '(
        SELECT concat(string_agg(product_categories.slug,\',\'),\'|\',string_agg(product_categories.type,\',\'),\'|\',string_agg(product_categories.code,\',\'),\'|\',string_agg(product_categories.name,\',\')) FROM model_has_collections
        left join product_categories on model_has_collections.model_id = product_categories.id
        WHERE model_has_collections.collection_id = collections.id
   
        AND model_has_collections.model_type = ?
    ) as parents_data',
                ['ProductCategory',]
            );


        return $queryBuilder
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['code', 'name', 'number_parents', 'number_families', 'number_products'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(MasterCollection $masterCollection, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __("No collections found"),
                        'description' => __('Get started by creating a new collection. ✨'),
                        'count'       => 0
                    ]
                );

            $table
                ->column(key: 'state_icon', label: '', canBeHidden: false, type: 'icon');
            $table
                ->column(key: 'shop_code', label: __('Shop'), canBeHidden: false);
            $table
                ->column(key: 'image_thumbnail', label: '', type: 'avatar');
            $table
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function htmlResponse(LengthAwarePaginator $collections, ActionRequest $request): Response
    {
        $subNavigation   = $this->getMasterCollectionSubNavigation($this->parent);
        $title           = $this->parent->name;
        $icon            = [
            'icon'  => ['fal', 'fa-album-collection'],
            'title' => __('Master Collections')
        ];
        $afterTitle      = [
            'label' => __('Collections in Shop')
        ];
        $iconRight       = [
            'icon' => 'fal fa-store',
        ];

        return Inertia::render(
            'Org/Catalogue/Collections',
            [
                'breadcrumbs'    => $this->getBreadcrumbs(
                    $this->parent,
                    request()->route()->getName(),
                    request()->route()->originalParameters()
                ),
                'title'          => __('Collections'),
                'pageHead'       => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                ],
                'tabs'           => [
                    'current'    => $this->tab,
                    'navigation' => CollectionsTabsEnum::navigationExcept([CollectionsTabsEnum::SALES]),
                ],
                'data'           => CollectionsResource::collection($collections),

                CollectionsTabsEnum::INDEX->value => $this->tab == CollectionsTabsEnum::INDEX->value ?
                    fn () => CollectionsResource::collection($collections)
                    : Inertia::lazy(fn () => CollectionsResource::collection($collections)),
            ]
        )
        ->table($this->tableStructure($this->parent, prefix: CollectionsTabsEnum::INDEX->value));
    }

    public function getBreadcrumbs(MasterCollection $parent, string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Collections in Shop'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.masters.master_shops.show.master_sub_departments.master_collections.collections',
            'grp.masters.master_shops.show.master_departments.show.master_collections.collections',
            'grp.masters.master_departments.show.master_collections.collections',
            'grp.masters.master_shops.show.master_collections.collections' =>
            array_merge(
                ShowMasterCollection::make()->getBreadcrumbs($this->parent, preg_replace('/collections$/', 'show', $routeName), $routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function asController(MasterShop $masterShop, MasterCollection $masterCollection, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterCollection;
        $group = group();

        $this->initialisation($group, $request)->withTab(CollectionsTabsEnum::valuesExcept([CollectionsTabsEnum::SALES]));

        return $this->handle($masterCollection, CollectionsTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterCollection $masterCollection, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterCollection;
        $group = group();

        $this->initialisation($group, $request)->withTab(CollectionsTabsEnum::valuesExcept([CollectionsTabsEnum::SALES]));

        return $this->handle($masterCollection, CollectionsTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterCollection $masterCollection, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterCollection;
        $group = group();

        $this->initialisation($group, $request)->withTab(CollectionsTabsEnum::valuesExcept([CollectionsTabsEnum::SALES]));

        return $this->handle($masterCollection, CollectionsTabsEnum::INDEX->value);
    }


    public function inGroup(MasterCollection $masterCollection, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterCollection;
        $group        = group();

        $this->initialisation($group, $request)->withTab(CollectionsTabsEnum::valuesExcept([CollectionsTabsEnum::SALES]));

        return $this->handle($masterCollection, CollectionsTabsEnum::INDEX->value);
    }
}
