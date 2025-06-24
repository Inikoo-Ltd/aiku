<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:05:53 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\Catalogue\Product\UI\IndexProductsInCollection;
use App\Actions\Catalogue\ProductCategory\UI\IndexFamiliesInCollection;
use App\Actions\Catalogue\ProductCategory\UI\ShowDepartment;
use App\Actions\Catalogue\ProductCategory\UI\ShowFamily;
use App\Actions\Catalogue\ProductCategory\UI\ShowSubDepartment;
use App\Actions\Catalogue\Shop\UI\IndexShops;
use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\Catalogue\CollectionTabsEnum;
use App\Http\Resources\Catalogue\CollectionResource;
use App\Http\Resources\Catalogue\FamiliesInCollectionResource;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowCollection extends OrgAction
{
    use WithCollectionSubNavigation;
    use WithCatalogueAuthorisation;

    private Organisation|Shop|ProductCategory $parent;

    public function handle(Collection $collection): Collection
    {
        return $collection;
    }

    public function inOrganisation(Organisation $organisation, Collection $collection, ActionRequest $request): Collection
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(CollectionTabsEnum::values());
        return $this->handle($collection);
    }

    public function asController(Organisation $organisation, Shop $shop, Collection $collection, ActionRequest $request): Collection
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(CollectionTabsEnum::values());
        return $this->handle($collection);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, Collection $collection, ActionRequest $request): Collection
    {
        $this->parent = $department;
        $this->initialisationFromShop($shop, $request)->withTab(CollectionTabsEnum::values());

        return $this->handle($collection);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFamily(Organisation $organisation, Shop $shop, ProductCategory $family, Collection $collection, ActionRequest $request): Collection
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request)->withTab(CollectionTabsEnum::values());

        return $this->handle($collection);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFamilyInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $family, Collection $collection, ActionRequest $request): Collection
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request)->withTab(CollectionTabsEnum::values());

        return $this->handle($collection);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFamilyInSubDepartmentInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ProductCategory $family, Collection $collection, ActionRequest $request): Collection
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request)->withTab(CollectionTabsEnum::values());

        return $this->handle($collection);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, Collection $collection, ActionRequest $request): Collection
    {
        $this->parent = $subDepartment;
        $this->initialisationFromShop($shop, $request)->withTab(CollectionTabsEnum::values());

        return $this->handle($collection);
    }

    public function htmlResponse(Collection $collection, ActionRequest $request): Response
    {
        $title = $collection->code;
        $model = __('collection');
        $icon = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => __('collection')
        ];
        $afterTitle = null;
        $iconRight = null;
        $container = null;

        if ($this->parent instanceof ProductCategory) {
            $title = $this->parent->name;
            $iconRight    = [
                'icon' => 'fal fa-album-collection',
            ];
            $afterTitle = [
                'label'     => __('Collection: :name', ['name' => $collection->name]),
            ];
            $model = '';
            if ($this->parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
                $icon  = [
                    'icon'  => ['fal', 'fa-folder-tree'],
                    'title' => __('department')
                ];
            } elseif ($this->parent->type == ProductCategoryTypeEnum::FAMILY) {
                $icon  = [
                    'icon'  => ['fal', 'fa-folder'],
                    'title' => __('family')
                ];
            } elseif ($this->parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $icon  = [
                    'icon'  => ['fal', 'fa-dot-circle'],
                    'title' => __('sub department')
                ];
            }
        } else {
            $iconRight = $collection->state->stateIcon()[$collection->state->value];
        }
        return Inertia::render(
            'Org/Catalogue/Collection',
            [
                'title'       => __('collection'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($collection, $request),
                    'next'     => $this->getNext($collection, $request),
                ],
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'container'     => $container,
                    'actions' => [
                        $collection->webpage ?
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'tooltip' => __('To Webpage'),
                            'label'   => __('To Webpage'),
                            'icon'  => ["fal", "fa-drafting-compass"],
                            'route' => [
                                'name'       => 'grp.org.shops.show.web.webpages.show',
                                'parameters' => [
                                    'organisation' => $this->organisation->slug,
                                    'shop'         => $this->shop->slug,
                                    'website'      => $this->shop->website->slug,
                                    'webpage'      => $collection->webpage->slug
                                ]
                            ]
                        ] : [
                            'type'  => 'button',
                            'style' => 'edit',
                            'tooltip' => __('Create Webpage'),
                            'label'   => __('Create Webpage'),
                            'icon'  => ["fas", "fa-plus"],
                            'route' => [
                                'name'       => 'grp.models.webpages.collection.store',
                                'parameters' => $collection->id,
                                'method'     => 'post'
                            ]
                        ],
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,

                    ],
                ],
                'routes' => [
                    'departments' => [
                        'dataList'  => [
                            'name'          => 'grp.json.shop.catalogue.departments',
                            'parameters'    => [
                                'shop'  => $collection->shop->slug,
                                'scope' => $collection->slug
                            ]
                        ],
                        'submitAttach'  => [
                            'name'          => 'grp.models.collection.attach-models',
                            'parameters'    => [
                                'collection' => $collection->id
                            ]
                        ],
                        'detach'        => [
                            'method'        => 'delete',
                            'name'          => 'grp.models.collection.detach-models',
                            'parameters'    => [
                                'collection' => $collection->id
                            ]
                        ]
                    ],
                    'families' => [
                        'dataList'  => [
                            'name'          => 'grp.json.shop.catalogue.families',
                            'parameters'    => [
                                'shop'  => $collection->shop->slug,
                                'scope' => $collection->slug
                            ]
                        ],
                        'submitAttach'  => [
                            'name'          => 'grp.models.collection.attach-models',
                            'parameters'    => [
                                'collection' => $collection->id
                            ]
                        ],
                        'detach'        => [
                            'method'        => 'delete',
                            'name'          => 'grp.models.collection.detach-models',
                            'parameters'    => [
                                'collection' => $collection->id
                            ]
                        ]
                    ],
                    'products' => [
                        'dataList'  => [
                            'name'          => 'grp.json.shop.catalogue.collection.products',
                            'parameters'    => [
                                'shop'  => $collection->shop->slug,
                                'scope' => $collection->slug
                            ]
                        ],
                        'submitAttach'  => [
                            'name'          => 'grp.models.collection.attach-models',
                            'parameters'    => [
                                'collection' => $collection->id
                            ]
                        ],
                        'detach'        => [
                            'method'        => 'delete',
                            'name'          => 'grp.models.collection.detach-models',
                            'parameters'    => [
                                'collection' => $collection->id
                            ]
                        ]
                    ],
                    'collections' => [
                        'dataList'  => [
                            'name'          => 'grp.json.shop.catalogue.collections',
                            'parameters'    => [
                                'shop'  => $collection->shop->slug,
                                'scope' => $collection->slug
                            ]
                        ],
                        'submitAttach'  => [
                            'name'          => 'grp.models.collection.attach-models',
                            'parameters'    => [
                                'collection' => $collection->id
                            ]
                        ],
                        'detach'        => [
                            'method'        => 'delete',
                            'name'          => 'grp.models.collection.detach-models',
                            'parameters'    => [
                                'collection' => $collection->id
                            ]
                        ]
                    ]
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => CollectionTabsEnum::navigation($collection)
                ],

                CollectionTabsEnum::SHOWCASE->value => $this->tab == CollectionTabsEnum::SHOWCASE->value ?
                    fn () => GetCollectionShowcase::run($collection)
                    : Inertia::lazy(fn () => GetCollectionShowcase::run($collection)),


                CollectionTabsEnum::FAMILIES->value => $this->tab == CollectionTabsEnum::FAMILIES->value ?
                    fn () => FamiliesInCollectionResource::collection(IndexFamiliesInCollection::run($collection))
                    : Inertia::lazy(fn () => FamiliesInCollectionResource::collection(IndexFamiliesInCollection::run($collection))),

                CollectionTabsEnum::PRODUCTS->value => $this->tab == CollectionTabsEnum::PRODUCTS->value ?
                    fn () => ProductsResource::collection(IndexProductsInCollection::run($collection))
                    : Inertia::lazy(fn () => ProductsResource::collection(IndexProductsInCollection::run($collection))),




            ]
        )

        ->table(
            IndexFamiliesInCollection::make()->tableStructure(
                collection:$collection,
                prefix: CollectionTabsEnum::FAMILIES->value,
            )
        )->table(
            IndexProductsInCollection::make()->tableStructure(
                collection:$collection,
                prefix: CollectionTabsEnum::PRODUCTS->value,
            )
        );

    }

    public function jsonResponse(Collection $collection): CollectionResource
    {
        return new CollectionResource($collection);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (Collection $collection, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Collections')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $collection->slug,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        $collection = Collection::where('slug', $routeParameters['collection'])->first();

        return match ($routeName) {
            'shops.collections.show' =>
            array_merge(
                IndexShops::make()->getBreadcrumbs('grp.org.shops.index', $routeParameters['organisation']),
                $headCrumb(
                    $routeParameters['collection'],
                    [
                        'index' => [
                            'name'       => 'shops.collections.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'shops.collections.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.collections.show' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $collection,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.collections.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.collections.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.collection.show' =>
            array_merge(
                ShowDepartment::make()->getBreadcrumbs('grp.org.shops.show.catalogue.departments.show', $routeParameters),
                $headCrumb(
                    $collection,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.collection.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.collection.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.families.show.collection.show' =>
            array_merge(
                ShowFamily::make()->getBreadcrumbs($this->parent, 'grp.org.shops.show.catalogue.departments.show.families.show', $routeParameters),
                $headCrumb(
                    $collection,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.families.show.collection.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.families.show.collection.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.show' =>
            array_merge(
                ShowSubDepartment::make()->getBreadcrumbs($this->parent, $routeParameters),
                $headCrumb(
                    $collection,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show.collection.show' =>
            array_merge(
                ShowFamily::make()->getBreadcrumbs($this->parent, 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show', $routeParameters),
                $headCrumb(
                    $collection,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show.collection.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show.collection.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.families.show.collection.show' =>
            array_merge(
                ShowFamily::make()->getBreadcrumbs($this->parent, 'grp.org.shops.show.catalogue.families.show', $routeParameters),
                $headCrumb(
                    $collection,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.families.show.collection.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.families.show.collection.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(Collection $collection, ActionRequest $request): ?array
    {
        $previous = Collection::where('slug', '<', $collection->slug)->orderBy('slug', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());

    }

    public function getNext(Collection $collection, ActionRequest $request): ?array
    {
        $next = Collection::where('slug', '>', $collection->slug)->orderBy('slug')->first();
        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Collection $collection, string $routeName): ?array
    {
        if (!$collection) {
            return null;
        }

        return match ($routeName) {
            'shops.org.collections.show' => [
                'label' => $collection->name,
                'route' => [
                    'name'      => $routeName,
                    'parameters' => [
                        'collection' => $collection->slug
                    ]

                ]
            ],
            'grp.org.shops.show.catalogue.collections.show' => [
                'label' => $collection->name,
                'route' => [
                    'name'      => $routeName,
                    'parameters' => [
                        'organisation'   => $this->organisation->slug,
                        'shop'           => $collection->shop->slug,
                        'collection'     => $collection->slug
                    ]

                ]
            ],
            default => null
        };
    }
}
