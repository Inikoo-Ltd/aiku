<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:35:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\ProductCategory\UI;

use App\Actions\Catalogue\ProductCategory\UI\GetProductCategoryShowcase;
use App\Actions\Retina\Dropshipping\Catalogue\ShowRetinaCatalogue;
use App\Actions\Retina\Dropshipping\Product\UI\IndexRetinaProductsInCatalogue;
use App\Actions\RetinaAction;
use App\Enums\UI\Catalogue\RetinaFamilyTabsEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Models\Catalogue\ProductCategory;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaFamily extends RetinaAction
{
    public function handle(ProductCategory $family): ProductCategory
    {
        return $family;
    }

    public function asController(ProductCategory $family, ActionRequest $request): ProductCategory
    {

        $this->initialisation($request)->withTab(RetinaFamilyTabsEnum::values());
        return $this->handle($family);
    }

    public function htmlResponse(ProductCategory $family, ActionRequest $request): Response
    {
        return Inertia::render(
            'Catalogue/RetinaFamily',
            [
                'title'       => __('family'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($family, $request),
                    'next'     => $this->getNext($family, $request),
                ],
                'pageHead'    => [
                    'title'         => $family->name,
                    'model'        => __('Family'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-folder'],
                        'title' => __('family')
                    ],
                    'iconRight' => $family->state->stateIcon()[$family->state->value],
                    'actions' => [
                        [
                            'route' => [
                                'name' => 'retina.models.portfolio.store_to_multi_channels',
                                'parameters' => [
                                    'family' => $family->id
                                ]
                            ],
                            'type' => 'button',
                            'style' => 'create',
                            'label' => __('to Portfolio'),
                        ]
                    ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => RetinaFamilyTabsEnum::navigation()
                ],
                "data" => [
                    'showcase' => $family->id,
                    'products' => ProductsResource::collection(
                        IndexRetinaProductsInCatalogue::run(
                            parent: $family,
                            prefix: RetinaFamilyTabsEnum::PRODUCTS->value
                        )
                    ),
                ],

                RetinaFamilyTabsEnum::SHOWCASE->value => $this->tab == RetinaFamilyTabsEnum::SHOWCASE->value ?
                    fn () => GetProductCategoryShowcase::run($family)
                    : Inertia::lazy(fn () => GetProductCategoryShowcase::run($family)),

                RetinaFamilyTabsEnum::PRODUCTS->value => $this->tab == RetinaFamilyTabsEnum::PRODUCTS->value
                    ?
                    fn () => ProductsResource::collection(
                        IndexRetinaProductsInCatalogue::run(
                            parent: $family,
                            prefix: RetinaFamilyTabsEnum::PRODUCTS->value
                        )
                    )
                    : Inertia::lazy(fn () => ProductsResource::collection(
                        IndexRetinaProductsInCatalogue::run(
                            parent: $family,
                            prefix: RetinaFamilyTabsEnum::PRODUCTS->value
                        )
                    )),

            ]
        )->table(
            IndexRetinaProductsInCatalogue::make()->tableStructure(
                shop: $family->shop,
                prefix: RetinaFamilyTabsEnum::PRODUCTS->value
            )
        );
    }


    public function jsonResponse(ProductCategory $family): DepartmentsResource
    {
        return new DepartmentsResource($family);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (ProductCategory $family, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Family')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $family->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        $department = ProductCategory::where('slug', $routeParameters['family'])->first();

        return match ($routeName) {

            'retina.catalogue.families.show' =>
            array_merge(
                ShowRetinaCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $department,
                    [
                        'index' => [
                            'name'       => 'retina.catalogue.families.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'retina.catalogue.families.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(ProductCategory $family, ActionRequest $request): ?array
    {
        $previous = ProductCategory::where('code', '<', $family->code)->where('shop_id', $this->shop->id)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(ProductCategory $family, ActionRequest $request): ?array
    {
        $next = ProductCategory::where('code', '>', $family->code)->where('shop_id', $this->shop->id)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?ProductCategory $family, string $routeName): ?array
    {
        if (!$family) {
            return null;
        }

        return match ($routeName) {

            'retina.catalogue.families.show' => [
                'label' => $family->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'family'   => $family->slug
                    ]
                ]
            ],
        };
    }
}
