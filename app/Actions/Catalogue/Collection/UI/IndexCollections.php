<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:05:41 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\Catalogue\WithSubDepartmentSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexCollections extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithCollectionSubNavigation;
    use WithDepartmentSubNavigation;
    use WithSubDepartmentSubNavigation;
    use WithFamilySubNavigation;


    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Collection::class);
        $queryBuilder->where('collections.shop_id', $shop->id);
        $queryBuilder->leftjoin('collection_stats', 'collections.id', 'collection_stats.collection_id');


        $queryBuilder
            ->leftJoin('webpages', function ($join) {
                $join->on('collections.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'Collection');
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
                'webpages.id as webpage_id',
                'webpages.state as webpage_state',
                'webpages.url as webpage_url',
                'webpages.slug as webpage_slug',
                'collections.name',
                'collections.description',
                'collections.created_at',
                'collections.updated_at',
                'collections.slug',
                'collection_stats.number_families',
                'collection_stats.number_products',
                'collection_stats.number_parents',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'websites.slug as website_slug',
            ])
            ->selectRaw(
                '(
        SELECT concat(string_agg(product_categories.slug,\',\'),\'|\',string_agg(product_categories.type,\',\'),\'|\',string_agg(product_categories.code,\',\'),\'|\',string_agg(product_categories.name,\',\')) FROM model_has_collections
        left join product_categories on model_has_collections.model_id = product_categories.id
        WHERE model_has_collections.collection_id = collections.id
   
        AND model_has_collections.model_type = ?
    ) as parents_data',
                ['ProductCategory', ]
            );


        return $queryBuilder
            ->allowedSorts(['code', 'name', 'number_parents', 'number_families', 'number_products'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(
        Shop $shop,
        $prefix = null,
    ): Closure {
        return function (InertiaTable $table) use ($shop, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __("No collections found"),
                        'description' => __('Get started by creating a new collection. ✨'),
                        'count'       => $shop->stats->number_collections,
                    ]
                );

            $table
                ->column(key: 'state_icon', label: '', canBeHidden: false, type: 'icon');
            $table->column(key: 'parents', label: __('Parents'), canBeHidden: false);

            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'webpage', label: __('Webpage'), canBeHidden: false);
            $table->column(key: 'number_families', label: __('Families'), canBeHidden: false);
            $table->column(key: 'number_products', label: __('Products'), canBeHidden: false);
            $table->column(key: 'actions', label: '', searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $collections): AnonymousResourceCollection
    {
        return CollectionsResource::collection($collections);
    }

    public function htmlResponse(LengthAwarePaginator $collections, ActionRequest $request): Response
    {
        $container = null;


        $subNavigation = null;

        $title      = __('Collections');
        $icon       = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => __('collections')
        ];
        $afterTitle = null;
        $iconRight  = null;


        $routes = null;

        $actions = [];

        if (!app()->isProduction()) {
            $actions = array_values(array_filter([
                ... (function () use ($request) {
                    if (!$this->canEdit) {
                        return [];
                    }

                    $routes = [
                        'grp.org.shops.show.catalogue.collections.index'                                                  => 'grp.org.shops.show.catalogue.collections.create',
                        'grp.org.shops.show.catalogue.departments.show.collection.index'                                  => 'grp.org.shops.show.catalogue.departments.show.collection.create',
                        'grp.org.shops.show.catalogue.departments.show.families.show.collection.index'                    => 'grp.org.shops.show.catalogue.departments.show.families.show.collection.create',
                        'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.index'             => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.create',
                        'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show.collection.index' => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show.collection.create',
                        'grp.org.shops.show.catalogue.families.show.collection.index'                                     => 'grp.org.shops.show.catalogue.families.show.collection.create',
                    ];

                    $currentRoute = $request->route()->getName();

                    if (!isset($routes[$currentRoute])) {
                        return [];
                    }

                    return [
                        [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new collection'),
                            'label'   => __('collection'),
                            'route'   => [
                                'name'       => $routes[$currentRoute],
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ]
                    ];
                })(),


            ]));
        }

        return Inertia::render(
            'Org/Catalogue/Collections',
            [
                'breadcrumbs'    => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'          => __('Collections'),
                'pageHead'       => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'container'     => $container,
                    'actions'       => $actions,
                    'subNavigation' => $subNavigation,
                ],
                'routes'         => $routes,
                'data'           => CollectionsResource::collection($collections),
                'formData'       => [
                    'fullLayout' => true,
                    'blueprint'  => [
                        [
                            'title'  => __('New Collection'),
                            'fields' => [
                                'code'        => [
                                    'type'     => 'input',
                                    'label'    => __('code'),
                                    'required' => true
                                ],
                                'name'        => [
                                    'type'     => 'input',
                                    'label'    => __('name'),
                                    'required' => true,
                                ],
                                'description' => [
                                    'type'     => 'textarea',
                                    'label'    => __('description'),
                                    'required' => false,
                                ],
                                "image"       => [
                                    "type"     => "image_crop_square",
                                    "label"    => __("Image"),
                                    "required" => false,
                                ],

                            ]
                        ]
                    ],
                    'route'      => [
                        'name'       => 'grp.models.org.catalogue.collections.store',
                        'parameters' => [
                            'organisation' => $this->shop->organisation_id,
                            'shop'         => $this->shop->id,
                        ]
                    ]
                ],
                'website_domain' => 'https://'.$this->shop->website->domain,
            ]
        )->table($this->tableStructure($this->shop));
    }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle(shop: $shop);
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
        return match ($routeName) {
            'grp.org.shops.show.catalogue.collections.index' ,
            'grp.org.shops.show.catalogue.collections.create' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),

            'grp.overview.catalogue.collections.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
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
}
