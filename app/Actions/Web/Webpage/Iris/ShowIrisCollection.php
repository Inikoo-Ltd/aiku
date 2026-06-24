<?php

namespace App\Actions\Web\Webpage\Iris;

use App\Actions\Iris\Catalogue\IndexIrisCatalogue;
use App\Actions\IrisAction;
use App\Enums\UI\Catalogue\IrisCollectionTabsEnum;
use App\Http\Resources\Catalogue\CollectionResource;
use App\Http\Resources\Catalogue\FamiliesResource;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Models\Catalogue\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowIrisCollection extends IrisAction
{
    public function handle(Collection $collection): Collection
    {
        return $collection;
    }

    public function asController(Collection $collection, ActionRequest $request): Collection
    {
        $this->initialisation($request)->withTab(IrisCollectionTabsEnum::values());

        return $this->handle($collection);
    }

    public function htmlResponse(Collection $collection, ActionRequest $request): Response
    {
        return Inertia::render(
            'Catalogue/Collection',
            [
                'catalogue_scope' => 'collection',
                'title'           => $collection->name,
                'pageHead'        => [
                    'title' => $collection->name,
                    'model' => __('Collection'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-album-collection'],
                        'title' => __('Collection'),
                    ],
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => IrisCollectionTabsEnum::navigation($collection),
                ],
                'mini_breadcrumbs' => array_filter([
                    [
                        'label'   => $collection->name,
                        'to'      => [
                            'name'       => 'iris.catalogue.collection.show',
                            'parameters' => [
                                'collection' => $collection->slug,
                            ],
                        ],
                        'tooltip' => __('Collection'),
                        'icon'    => ['fal', 'album-collection'],
                    ],
                ]),

                'data' => [
                    'collection' => CollectionResource::make($collection)->resolve(),
                ],

                IrisCollectionTabsEnum::FAMILIES->value => $this->tab == IrisCollectionTabsEnum::FAMILIES->value
                    ?
                    fn () => FamiliesResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'family',
                                'parent'     => 'collection',
                                'parent_key' => $collection->id,
                            ],
                            $request,
                            IrisCollectionTabsEnum::FAMILIES->value
                        )
                    )
                    : Inertia::lazy(fn () => FamiliesResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'family',
                                'parent'     => 'collection',
                                'parent_key' => $collection->id,
                            ],
                            $request,
                            IrisCollectionTabsEnum::FAMILIES->value
                        )
                    )),

                IrisCollectionTabsEnum::PRODUCTS->value => $this->tab == IrisCollectionTabsEnum::PRODUCTS->value
                    ?
                    fn () => ProductsResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'product',
                                'parent'     => 'collection',
                                'parent_key' => $collection->id,
                            ],
                            $request,
                            IrisCollectionTabsEnum::PRODUCTS->value
                        )
                    )
                    : Inertia::lazy(fn () => ProductsResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'product',
                                'parent'     => 'collection',
                                'parent_key' => $collection->id,
                            ],
                            $request,
                            IrisCollectionTabsEnum::PRODUCTS->value
                        )
                    )),
            ]
        )->table(
            IndexIrisCatalogue::make()->tableStructure(
                scope: 'family',
                parent: 'collection',
                prefix: IrisCollectionTabsEnum::FAMILIES->value
            )
        )->table(
            IndexIrisCatalogue::make()->tableStructure(
                scope: 'product',
                parent: 'collection',
                prefix: IrisCollectionTabsEnum::PRODUCTS->value
            )
        );
    }
}
