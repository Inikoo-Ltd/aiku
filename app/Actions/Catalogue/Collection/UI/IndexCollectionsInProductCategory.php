<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Jun 2025 00:01:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\Catalogue\ProductCategory\UI\ShowDepartment;
use App\Actions\Catalogue\ProductCategory\UI\ShowSubDepartment;
use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\Catalogue\WithSubDepartmentSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexCollectionsInProductCategory extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithCollectionSubNavigation;
    use WithDepartmentSubNavigation;
    use WithSubDepartmentSubNavigation;
    use WithFamilySubNavigation;



    private ProductCategory $parent;

    public function handle(ProductCategory $parent, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Collection::class);
        $queryBuilder->join('model_has_collections', function ($join) {
            $join->on('model_has_collections.collection_id', '=', 'collections.id');
        });
        $queryBuilder->where('model_has_collections.model_id', $parent->id);
        $queryBuilder->where('model_has_collections.model_type', 'ProductCategory');

        $queryBuilder->leftjoin('collection_stats', 'collections.id', 'collection_stats.collection_id');

        $queryBuilder
            ->leftJoin('webpages', function ($join) {
                $join->on('collections.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'Collection');
            });

        $queryBuilder
            ->leftJoin('shops', 'collections.shop_id', '=', 'shops.id')
            ->leftJoin('websites', 'websites.shop_id', '=', 'shops.id');


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
                'collection_stats.number_parents',
                'webpages.id as webpage_id',
                'webpages.state as webpage_state',
                'webpages.url as webpage_url',
                'webpages.slug as webpage_slug',
                'websites.slug as website_slug',
            ]);



        return $queryBuilder
            ->allowedSorts(['code', 'name', 'number_families', 'number_products'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(ProductCategory $productCategory, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($productCategory, $modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }


            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => __("No collections in this product category"),
                        'count' => $productCategory->stats->number_collections,
                    ]
                );

            $table
                ->column(key: 'state_icon', label: '', canBeHidden: false, type: 'icon')
            ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
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
        $productCategory = $this->parent;
        $container = null;


        $subNavigation = null;


        $icon       = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => __('collections')
        ];


        $title      = $productCategory->name;
        $iconRight  = [
            'icon' => 'fal fa-album-collection',
        ];
        $afterTitle = [
            'label' => __('Collections')
        ];
        $model      = '';
        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $icon          = [
                'icon'  => ['fal', 'fa-folder-tree'],
                'title' => __('department')
            ];
            $subNavigation = $this->getDepartmentSubNavigation($productCategory);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $icon          = [
                'icon'  => ['fal', 'fa-dot-circle'],
                'title' => __('sub department')
            ];
            $subNavigation = $this->getSubDepartmentSubNavigation($productCategory);
        }




        $actions = array_values(array_filter([
            ... (function () use ($request) {
                if (!$this->canEdit) {
                    return [];
                }

                $routes = [
                    'grp.org.shops.show.catalogue.departments.show.collection.index'                                  => 'grp.org.shops.show.catalogue.departments.show.collection.create',
                    'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.index'             => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.create',
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


        return Inertia::render(
            'Org/Catalogue/Collections',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $productCategory,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Collections'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'container'     => $container,
                    'actions'       => $actions,
                    'subNavigation' => $subNavigation,
                ],
                'routes'      => null,
                'data'        => CollectionsResource::collection($collections),
                'formData'    => [
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
                        'name'       => 'grp.models.product_category.collection.store',
                        'parameters' => [
                            'productCategory' => $productCategory->id,
                        ]
                    ]

                ],
            ]
        )->table($this->tableStructure($productCategory));
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $department;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $department);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $subDepartment;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $subDepartment);
    }

    public function getBreadcrumbs(ProductCategory $parent, string $routeName, array $routeParameters, string $suffix = null): array
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


            'grp.org.shops.show.catalogue.departments.show.collection.index' => array_merge(
                ShowDepartment::make()->getBreadcrumbs('grp.org.shops.show.catalogue.departments.show', $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.catalogue.departments.show.collection.index',
                        'parameters' => [
                            $routeParameters['organisation'],
                            $routeParameters['shop'],
                            $routeParameters['department']
                        ]
                    ],
                    $suffix
                )
            ),

            'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.index' => array_merge(
                ShowSubDepartment::make()->getBreadcrumbs($parent, $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.index',
                        'parameters' => [
                            $routeParameters['organisation'],
                            $routeParameters['shop'],
                            $routeParameters['department'],
                            $routeParameters['subDepartment']
                        ]
                    ],
                    $suffix
                )
            ),



            default => []
        };
    }
}
