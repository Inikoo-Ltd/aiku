<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:05:41 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\Catalogue\ProductCategory\UI\ShowDepartment;
use App\Actions\Catalogue\ProductCategory\UI\ShowFamily;
use App\Actions\Catalogue\ProductCategory\UI\ShowSubDepartment;
use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\Catalogue\WithSubDepartmentSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\CollectionResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexCollection extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithCollectionSubNavigation;
    use WithDepartmentSubNavigation;
    use WithSubDepartmentSubNavigation;
    use WithFamilySubNavigation;

    private Group|Shop|Organisation|Collection|ProductCategory $parent;

    public function handle(Group|Shop|Organisation|Collection|ProductCategory $parent, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Collection::class);

        $queryBuilder
            ->defaultSort('collections.code')
            ->select([
                'collections.id',
                'collections.code',
                'collections.name',
                'collections.description',
                'collections.created_at',
                'collections.updated_at',
                'collections.slug',
            ]);

        if ($parent instanceof Group) {
            $queryBuilder->where('collections.group_id', $parent->id)
                            ->leftJoin('organisations', 'collections.organisation_id', '=', 'organisations.id')
                            ->leftJoin('shops', 'collections.shop_id', '=', 'shops.id')
                            ->addSelect([
                                'shops.name as shop_name',
                                'shops.slug as shop_slug',
                                'organisations.name as organisation_name',
                                'organisations.slug as organisation_slug',
                            ]);
        } elseif (class_basename($parent) == 'Shop') {
            $queryBuilder->where('collections.shop_id', $parent->id);
            $queryBuilder->leftJoin('shops', 'collections.shop_id', 'shops.id');
            $queryBuilder->addSelect(
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'shops.name as shop_name',
            );
        } elseif (class_basename($parent) == 'Organisation') {
            $queryBuilder->where('collections.organisation_id', $parent->id);

        } elseif (class_basename($parent) == 'Collection') {
            $queryBuilder->join('model_has_collections', function ($join) use ($parent) {
                $join->on('collections.id', '=', 'model_has_collections.model_id')
                        ->where('model_has_collections.model_type', '=', 'Collection')
                        ->where('model_has_collections.collection_id', '=', $parent->id);
            });
        } elseif ($parent instanceof ProductCategory) {
            $queryBuilder->where('collections.parent_id', $parent->id)
                        ->where('collections.parent_type', class_basename($parent));
        } else {
            abort(419);
        }


        return $queryBuilder
            ->allowedSorts(['code', 'name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(
        Group|Shop|Organisation|Collection|ProductCategory $parent,
        ?array $modelOperations = null,
        $prefix = null,
        $canEdit = false
    ): Closure {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $canEdit) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }


            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Organisation' => [
                            'title'       => __("No departments found"),
                            'description' => $canEdit && $parent->catalogueStats->number_shops == 0 ? __('Get started by creating a shop. ✨') : '',
                            'count'       => $parent->catalogueStats->number_departments,
                            'action'      => $canEdit && $parent->catalogueStats->number_shops == 0 ?
                                [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('new shop'),
                                'label'   => __('shop'),
                                'route'   => [
                                    'name'       => 'grp.org.shops.create',
                                    'parameters' => [$parent->slug]
                                ]
                            ] : null

                        ],
                        'Shop' => [
                            'title'       => __("No collections found"),
                            'description' => __('Get started by creating a new collection. ✨'),
                            'count'       => $parent->stats->number_collections,
                            'action'      => [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('new collection'),
                                'label'   => __('collection'),
                                'route'   => [
                                    'name'           => 'grp.org.shops.show.catalogue.collections.create', //creating
                                        'parameters' => [$parent->organisation->slug,$parent->slug]
                                ]
                            ]
                        ],
                        default => null
                    }
                );

            $table
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true)
                        ->column(key: 'shop_name', label: __('shop'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'description', label: __('Description'), canBeHidden: false, sortable: false, searchable: true);
            if ($parent instanceof Collection) {
                $table->column(key: 'actions', label: __('action'), canBeHidden: false, sortable: true, searchable: true);
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $collections): AnonymousResourceCollection
    {
        return CollectionResource::collection($collections);
    }

    public function htmlResponse(LengthAwarePaginator $collections, ActionRequest $request): Response
    {

        $scope     = $this->parent;
        $container = null;
        if (class_basename($scope) == 'Shop') {
            $container = [
                'icon'    => ['fal', 'fa-store-alt'],
                'tooltip' => __('Shop'),
                'label'   => Str::possessive($scope->name)
            ];
        }

        $subNavigation = null;

        $title = __('Collections');
        $model = __('collection');
        $icon  = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => __('collections')
        ];
        $afterTitle = null;
        $iconRight = null;

        if ($this->parent instanceof Collection) {
            $subNavigation = $this->getCollectionSubNavigation($this->parent);
            $title = $this->parent->name;
            $model = __('collection');
            $icon  = [
                'icon'  => ['fal', 'fa-cube'],
                'title' => __('collection')
            ];
            $iconRight    = [
                'icon' => 'fal fa-album-collection',
            ];
            $afterTitle = [
                'label'     => __('Collections')
            ];
        } elseif ($this->parent instanceof ProductCategory) {
            $title = $this->parent->name;
            $iconRight    = [
                'icon' => 'fal fa-album-collection',
            ];
            $afterTitle = [
                'label'     => __('Collections')
            ];
            $model = '';
            if ($this->parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
                $icon  = [
                    'icon'  => ['fal', 'fa-folder-tree'],
                    'title' => __('department')
                ];
                $subNavigation = $this->getDepartmentSubNavigation($this->parent);
            } elseif ($this->parent->type == ProductCategoryTypeEnum::FAMILY) {
                $icon  = [
                    'icon'  => ['fal', 'fa-folder'],
                    'title' => __('family')
                ];
                $subNavigation = $this->getFamilySubNavigation($this->parent, $this->parent, $request);
            } elseif ($this->parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $icon  = [
                    'icon'  => ['fal', 'fa-dot-circle'],
                    'title' => __('sub department')
                ];
                $subNavigation = $this->getSubDepartmentSubNavigation($this->parent);
            }
        }

        $routes = null;
        if ($this->parent instanceof Collection) {
            $routes = [
                        'dataList'  => [
                            'name'          => 'grp.json.shop.catalogue.collections',
                            'parameters'    => [
                                'shop'  => $this->parent->shop->slug,
                                'scope' => $this->parent->slug
                            ]
                        ],
                        'submitAttach'  => [
                            'name'          => 'grp.models.collection.attach-models',
                            'parameters'    => [
                                'collection' => $this->parent->id
                            ]
                        ],
                        'detach'        => [
                            'name'          => 'grp.models.collection.detach-models',
                            'parameters'    => [
                                'collection' => $this->parent->id
                            ]
                        ]
                    ];
        }

        $actions = array_values(array_filter([
            ... (function () use ($request) {
                if (!$this->canEdit) {
                    return [];
                }

                $routes = [
                    'grp.org.shops.show.catalogue.collections.index'                                      => 'grp.org.shops.show.catalogue.collections.create',
                    'grp.org.shops.show.catalogue.departments.show.collection.index'                     => 'grp.org.shops.show.catalogue.departments.show.collection.create',
                    'grp.org.shops.show.catalogue.departments.show.families.show.collection.index'       => 'grp.org.shops.show.catalogue.departments.show.families.show.collection.create',
                    'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.index' => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.create',
                    'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show.collection.index' => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show.collection.create',
                    'grp.org.shops.show.catalogue.families.show.collection.index'                        => 'grp.org.shops.show.catalogue.families.show.collection.create',
                ];

                $currentRoute = $request->route()->getName();

                if (!isset($routes[$currentRoute])) {
                    return [];
                }

                return [[
                    'type'    => 'button',
                    'style'   => 'create',
                    'tooltip' => __('new collection'),
                    'label'   => __('collection'),
                    'route'   => [
                        'name'       => $routes[$currentRoute],
                        'parameters' => $request->route()->originalParameters()
                    ]
                ]];
            })(),

            class_basename($this->parent) === 'Collection' ? [
                'type'     => 'button',
                'style'    => 'secondary',
                'key'      => 'attach-collection',
                'icon'     => 'fal fa-plus',
                'tooltip'  => __('Attach collection to this collection'),
                'label'    => __('Attach collection'),
            ] : false
        ]));
        return Inertia::render(
            'Org/Catalogue/Collections',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => __('Collections'),
                'pageHead' => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'container'     => $container,
                    'actions'       => $actions,
                    'subNavigation' => $subNavigation,
                ],
                'routes'        => $routes,
                'data'          => CollectionResource::collection($collections),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->parent);
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);
        return $this->handle(parent: $organisation);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);
        return $this->handle(parent: $shop);
    }

    public function inCollection(Organisation $organisation, Shop $shop, Collection $collection, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $collection;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $collection);
    }

    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $department;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $department);
    }

    public function inFamily(Organisation $organisation, Shop $shop, ProductCategory $family, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $family);
    }

    public function inFamilyInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $family, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $family);
    }

    public function inFamilyInSubDepartmentInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ProductCategory $family, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $family);
    }

    public function inSubDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $subDepartment;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $subDepartment);
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
            'grp.org.shops.show.catalogue.collections.index' =>
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
            'grp.org.shops.show.catalogue.collections.collections.index' =>
            array_merge(
                ShowCollection::make()->getBreadcrumbs('grp.org.shops.show.catalogue.collections.show', $routeParameters),
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
                ShowGroupOverviewHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
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
            'grp.org.shops.show.catalogue.departments.show.families.show.collection.index' => array_merge(
                ShowFamily::make()->getBreadcrumbs($this->parent, 'grp.org.shops.show.catalogue.departments.show.families.show', $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.catalogue.departments.show.families.show.collection.index',
                        'parameters' => [
                            $routeParameters['organisation'],
                            $routeParameters['shop'],
                            $routeParameters['department'],
                            $routeParameters['family']
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.index' => array_merge(
                ShowSubDepartment::make()->getBreadcrumbs($this->parent, $routeParameters),
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
            'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show.collection.index' => array_merge(
                ShowFamily::make()->getBreadcrumbs($this->parent, 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show', $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show.collection.index',
                        'parameters' => [
                            $routeParameters['organisation'],
                            $routeParameters['shop'],
                            $routeParameters['department'],
                            $routeParameters['subDepartment'],
                            $routeParameters['family'],
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.families.show.collection.index' => array_merge(
                ShowFamily::make()->getBreadcrumbs($this->parent, 'grp.org.shops.show.catalogue.families.show', $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.catalogue.families.show.collection.index',
                        'parameters' => [
                            $routeParameters['organisation'],
                            $routeParameters['shop'],
                            $routeParameters['family'],
                        ]
                    ],
                    $suffix
                )
            ),

            default => []
        };
    }
}
