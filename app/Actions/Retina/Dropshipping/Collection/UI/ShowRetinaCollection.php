<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:05:53 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Retina\Dropshipping\Collection\UI;

use App\Actions\Catalogue\Collection\UI\GetCollectionShowcase;
use App\Actions\Catalogue\Collection\UI\IndexCollectionsInCollection;
use App\Actions\Catalogue\Product\UI\IndexProductsInCollection;
use App\Actions\Catalogue\ProductCategory\UI\IndexFamiliesInCollection;
use App\Actions\Retina\Dropshipping\Catalogue\ShowRetinaCatalogue;
use App\Actions\RetinaAction;
use App\Enums\UI\Catalogue\RetinaCollectionTabsEnum;
use App\Http\Resources\Catalogue\CollectionResource;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\Http\Resources\Catalogue\FamiliesInCollectionResource;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Models\Catalogue\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaCollection extends RetinaAction
{
    public function handle(Collection $collection): Collection
    {
        return $collection;
    }

    public function asController(Collection $collection, ActionRequest $request): Collection
    {
        $this->initialisation($request)->withTab(RetinaCollectionTabsEnum::values());

        return $this->handle($collection);
    }

    public function htmlResponse(Collection $collection, ActionRequest $request): Response
    {
        $title      = $collection->code;
        $model      = __('collection');
        $icon       = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => __('collection')
        ];
        $afterTitle = null;
        $container  = null;

        return Inertia::render(
            'Catalogue/RetinaCollection',
            [
                'title'       => __('collection'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($collection, $request),
                    'next'     => $this->getNext($collection, $request),
                ],
                'pageHead'    => [
                    'title'      => $title,
                    'icon'       => $icon,
                    'model'      => $model,
                    'afterTitle' => $afterTitle,
                    'container'  => $container,
                    'exports' => [
                        [
                            'routes' => [
                                [
                                    'label' => 'CSV',
                                    'key'   => 'csv',
                                    'icon' => ['fal', 'fa-file-csv'],
                                    'popover' => false,
                                    'route' => [
                                        'name' => 'retina.catalogue.feeds.collection.download',
                                        'parameters' => [
                                            'collection' => $collection->slug,
                                            'type'       => 'products_csv'
                                        ]
                                    ],
                                ],
                                [
                                    'label' => 'images',
                                    'key'   => 'images',
                                    'icon' => ['fal', 'fa-images'],
                                    'inside_popover' => true,
                                    'route' => [
                                        'name' => 'retina.catalogue.feeds.collection.download',
                                        'parameters' => [
                                            'collection' => $collection->slug,
                                            'type'       => 'products_images'
                                        ]
                                    ],
                                ]
                            ]
                        ]
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => RetinaCollectionTabsEnum::navigation($collection)
                ],

                RetinaCollectionTabsEnum::SHOWCASE->value => $this->tab == RetinaCollectionTabsEnum::SHOWCASE->value ?
                    fn() => GetCollectionShowcase::run($collection)
                    : Inertia::lazy(fn() => GetCollectionShowcase::run($collection)),


                RetinaCollectionTabsEnum::FAMILIES->value => $this->tab == RetinaCollectionTabsEnum::FAMILIES->value ?
                    fn() => FamiliesInCollectionResource::collection(IndexFamiliesInCollection::run($collection, prefix: RetinaCollectionTabsEnum::FAMILIES->value))
                    : Inertia::lazy(fn() => FamiliesInCollectionResource::collection(IndexFamiliesInCollection::run($collection, prefix: RetinaCollectionTabsEnum::FAMILIES->value))),

                RetinaCollectionTabsEnum::PRODUCTS->value => $this->tab == RetinaCollectionTabsEnum::PRODUCTS->value ?
                    fn() => ProductsResource::collection(IndexProductsInCollection::run($collection, prefix: RetinaCollectionTabsEnum::PRODUCTS->value))
                    : Inertia::lazy(fn() => ProductsResource::collection(IndexProductsInCollection::run($collection, prefix: RetinaCollectionTabsEnum::PRODUCTS->value))),

                RetinaCollectionTabsEnum::COLLECTIONS->value => $this->tab == RetinaCollectionTabsEnum::COLLECTIONS->value ?
                    fn() => CollectionsResource::collection(IndexCollectionsInCollection::run($collection, prefix: RetinaCollectionTabsEnum::COLLECTIONS->value))
                    : Inertia::lazy(fn() => CollectionsResource::collection(IndexCollectionsInCollection::run($collection, prefix: RetinaCollectionTabsEnum::COLLECTIONS->value))),
            ]
        )
            ->table(
                IndexFamiliesInCollection::make()->tableStructure(
                    collection: $collection,
                    prefix: RetinaCollectionTabsEnum::FAMILIES->value,
                    action: false
                )
            )->table(
                IndexProductsInCollection::make()->tableStructure(
                    collection: $collection,
                    prefix: RetinaCollectionTabsEnum::PRODUCTS->value,
                    action: false
                )
            )->table(
                IndexCollectionsInCollection::make()->tableStructure(
                    collection: $collection,
                    prefix: RetinaCollectionTabsEnum::COLLECTIONS->value,
                    action: false
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
                            'label' => $collection->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        $collection = Collection::where('slug', $routeParameters['collection'])->first();

        return match ($routeName) {

            'retina.catalogue.collections.show' =>
            array_merge(
                ShowRetinaCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $collection,
                    [
                        'index' => [
                            'name'       => 'retina.catalogue.collections.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'retina.catalogue.collections.show',
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
            'retina.catalogue.collections.show' => [
                'label' => $collection->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'collection' => $collection->slug
                    ]

                ]
            ],
            default => null
        };
    }
}
