<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:35:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\ProductCategory\UI;

use App\Actions\Catalogue\ProductCategory\UI\GetProductCategoryShowcase;
use App\Actions\Retina\Dropshipping\Catalogue\ShowRetinaCatalogue;
use App\Actions\Retina\Dropshipping\Collection\UI\IndexRetinaCollections;
use App\Actions\Retina\Dropshipping\Product\UI\IndexRetinaProductsInCatalogue;
use App\Actions\Retina\Dropshipping\ProductCategory\UI\IndexRetinaFamilies;
use App\Actions\Retina\Dropshipping\ProductCategory\UI\IndexRetinaSubDepartments;
use App\Actions\RetinaAction;
use App\Enums\UI\Catalogue\RetinaDepartmentTabsEnum;
use App\Enums\UI\Catalogue\RetinaSubDepartmentTabsEnum;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\Catalogue\FamiliesResource;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Http\Resources\Catalogue\SubDepartmentsResource;
use App\Models\Catalogue\ProductCategory;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaSubDepartment extends RetinaAction
{
    public function handle(ProductCategory $subDepartment): ProductCategory
    {
        return $subDepartment;
    }

    public function asController(ProductCategory $subDepartment, ActionRequest $request): ProductCategory
    {

        $this->initialisation($request)->withTab(RetinaSubDepartmentTabsEnum::values());
        return $this->handle($subDepartment);
    }

    public function htmlResponse(ProductCategory $subDepartment, ActionRequest $request): Response
    {
        return Inertia::render(
            'Catalogue/RetinaSubDepartement',
            [
                'title'       => __('sub department'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($subDepartment, $request),
                    'next'     => $this->getNext($subDepartment, $request),
                ],
                'pageHead'    => [
                    'title'         => $subDepartment->name,
                    'model'        => __('Sub Department'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-dot-circle'],
                        'title' => __('sub department')
                    ],
                    'iconRight' => $subDepartment->state->stateIcon()[$subDepartment->state->value],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => RetinaSubDepartmentTabsEnum::navigation()
                ],
                'actions' => [
                        [
                            'route' => [
                                'name' => 'retina.models.portfolio.store_to_multi_channels',
                                'parameters' => [
                                    'family' => $subDepartment->id
                                ]
                            ],
                            'type' => 'button',
                            'style' => 'create',
                            'label' => __('to Portfolio'),
                        ]
                        ],
                "data" => [
                    'showcase' => $subDepartment->id,
                    'products' => ProductsResource::collection(
                        IndexRetinaProductsInCatalogue::run(
                            parent: $subDepartment,
                            prefix: RetinaSubDepartmentTabsEnum::PRODUCTS->value
                        )
                    ),
                ],

                RetinaSubDepartmentTabsEnum::SHOWCASE->value => $this->tab == RetinaSubDepartmentTabsEnum::SHOWCASE->value ?
                    fn () => GetProductCategoryShowcase::run($subDepartment)
                    : Inertia::lazy(fn () => GetProductCategoryShowcase::run($subDepartment)),

                RetinaSubDepartmentTabsEnum::FAMILIES->value => $this->tab == RetinaSubDepartmentTabsEnum::FAMILIES->value
                    ?
                    fn () => FamiliesResource::collection(
                        IndexRetinaFamilies::run(
                            parent: $subDepartment,
                            prefix: RetinaSubDepartmentTabsEnum::FAMILIES->value
                        )
                    )
                    : Inertia::lazy(fn () => FamiliesResource::collection(
                        IndexRetinaSubDepartments::run(
                            parent: $subDepartment,
                            prefix: RetinaSubDepartmentTabsEnum::FAMILIES->value
                        )
                    )),

                RetinaSubDepartmentTabsEnum::PRODUCTS->value => $this->tab == RetinaSubDepartmentTabsEnum::PRODUCTS->value
                    ?
                    fn () => ProductsResource::collection(
                        IndexRetinaProductsInCatalogue::run(
                            parent: $subDepartment,
                            prefix: RetinaSubDepartmentTabsEnum::PRODUCTS->value
                        )
                    )
                    : Inertia::lazy(fn () => ProductsResource::collection(
                        IndexRetinaProductsInCatalogue::run(
                            parent: $subDepartment,
                            prefix: RetinaSubDepartmentTabsEnum::PRODUCTS->value
                        )
                    )),

                RetinaSubDepartmentTabsEnum::COLLECTIONS->value => $this->tab == RetinaSubDepartmentTabsEnum::COLLECTIONS->value
                    ?
                    fn () => CollectionsResource::collection(
                        IndexRetinaCollections::run(
                            parent: $subDepartment,
                            prefix: RetinaSubDepartmentTabsEnum::COLLECTIONS->value
                        )
                    )
                    : Inertia::lazy(fn () => CollectionsResource::collection(
                        IndexRetinaCollections::run(
                            parent: $subDepartment,
                            prefix: RetinaSubDepartmentTabsEnum::COLLECTIONS->value
                        )
                    )),

            ]
        )->table(
            IndexRetinaFamilies::make()->tableStructure(
                parent: $subDepartment,
                prefix: RetinaSubDepartmentTabsEnum::FAMILIES->value
            )
        )->table(
            IndexRetinaProductsInCatalogue::make()->tableStructure(
                shop: $subDepartment->shop,
                prefix: RetinaSubDepartmentTabsEnum::PRODUCTS->value
            )
        )->table(
            IndexRetinaCollections::make()->tableStructure(
                shop: $subDepartment->shop,
                prefix: RetinaSubDepartmentTabsEnum::COLLECTIONS->value
            )
        );
    }


    public function jsonResponse(ProductCategory $subDepartment): DepartmentsResource
    {
        return new DepartmentsResource($subDepartment);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (ProductCategory $subDepartment, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Sub Departments')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $subDepartment->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        $department = ProductCategory::where('slug', $routeParameters['subDepartment'])->first();

        return match ($routeName) {

            'retina.catalogue.sub_departments.show' =>
            array_merge(
                ShowRetinaCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $department,
                    [
                        'index' => [
                            'name'       => 'retina.catalogue.sub_departments.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'retina.catalogue.sub_departments.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(ProductCategory $subDepartment, ActionRequest $request): ?array
    {
        $previous = ProductCategory::where('code', '<', $subDepartment->code)->where('shop_id', $this->shop->id)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(ProductCategory $subDepartment, ActionRequest $request): ?array
    {
        $next = ProductCategory::where('code', '>', $subDepartment->code)->where('shop_id', $this->shop->id)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?ProductCategory $subDepartment, string $routeName): ?array
    {
        if (!$subDepartment) {
            return null;
        }

        return match ($routeName) {

            'retina.catalogue.sub_departments.show' => [
                'label' => $subDepartment->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'subDepartment'   => $subDepartment->slug
                    ]
                ]
            ],
        };
    }
}
