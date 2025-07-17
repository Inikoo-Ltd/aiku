<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:35:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Catalogue\ShowRetinaCatalogue;
use App\Actions\Retina\Dropshipping\Collection\UI\IndexRetinaCollections;
use App\Actions\Retina\Dropshipping\Product\UI\IndexRetinaProductsInCatalogue;
use App\Actions\Retina\Dropshipping\ProductCategory\UI\IndexRetinaFamilies;
use App\Actions\Retina\Dropshipping\ProductCategory\UI\IndexRetinaSubDepartments;
use App\Actions\RetinaAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\UI\Catalogue\DepartmentTabsEnum;
use App\Enums\UI\Catalogue\RetinaDepartmentTabsEnum;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\Catalogue\FamiliesResource;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Http\Resources\Catalogue\SubDepartmentsResource;
use App\Http\Resources\CRM\CustomersResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaDepartment extends RetinaAction
{
    public function handle(ProductCategory $department): ProductCategory
    {
        return $department;
    }


    public function asController(ProductCategory $department, ActionRequest $request): ProductCategory
    {

        $this->initialisation($request)->withTab(DepartmentTabsEnum::values());
        return $this->handle($department);
    }

    public function htmlResponse(ProductCategory $department, ActionRequest $request): Response
    {

        return Inertia::render(
            'Org/Catalogue/Department',
            [
                'title'       => __('department'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($department, $request),
                    'next'     => $this->getNext($department, $request),
                ],
                'pageHead'    => [
                    'title'         => $department->name,
                    'model'        => __('Department'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-folder-tree'],
                        'title' => __('department')
                    ],
                    'iconRight' => $department->state->stateIcon()[$department->state->value],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => DepartmentTabsEnum::navigation()
                ],

                RetinaDepartmentTabsEnum::SHOWCASE->value => $this->tab == RetinaDepartmentTabsEnum::SHOWCASE->value ?
                    fn () => GetProductCategoryShowcase::run($department)
                    : Inertia::lazy(fn () => GetProductCategoryShowcase::run($department)),

                RetinaDepartmentTabsEnum::SUB_DEPARTMENTS->value => $this->tab == RetinaDepartmentTabsEnum::SUB_DEPARTMENTS->value
                    ?
                    fn () => SubDepartmentsResource::collection(
                        IndexRetinaSubDepartments::run(
                            parent: $department,
                            prefix: RetinaDepartmentTabsEnum::SUB_DEPARTMENTS->value
                        )
                    )
                    : Inertia::lazy(fn () => SubDepartmentsResource::collection(
                        IndexRetinaSubDepartments::run(
                            parent: $department,
                            prefix: RetinaDepartmentTabsEnum::SUB_DEPARTMENTS->value
                        )
                    )),

                RetinaDepartmentTabsEnum::FAMILIES->value => $this->tab == RetinaDepartmentTabsEnum::FAMILIES->value
                    ?
                    fn () => FamiliesResource::collection(
                        IndexRetinaFamilies::run(
                            parent: $department,
                            prefix: RetinaDepartmentTabsEnum::FAMILIES->value
                        )
                    )
                    : Inertia::lazy(fn () => FamiliesResource::collection(
                        IndexRetinaSubDepartments::run(
                            parent: $department,
                            prefix: RetinaDepartmentTabsEnum::FAMILIES->value
                        )
                    )),

                RetinaDepartmentTabsEnum::PRODUCTS->value => $this->tab == RetinaDepartmentTabsEnum::PRODUCTS->value
                    ?
                    fn () => ProductsResource::collection(
                        IndexRetinaProductsInCatalogue::run(
                            parent: $department,
                            prefix: RetinaDepartmentTabsEnum::PRODUCTS->value
                        )
                    )
                    : Inertia::lazy(fn () => ProductsResource::collection(
                        IndexRetinaProductsInCatalogue::run(
                            parent: $department,
                            prefix: RetinaDepartmentTabsEnum::PRODUCTS->value
                        )
                    )),

                RetinaDepartmentTabsEnum::COLLECTIONS->value => $this->tab == RetinaDepartmentTabsEnum::COLLECTIONS->value
                    ?
                    fn () => CollectionsResource::collection(
                        IndexRetinaCollections::run(
                            parent: $department,
                            prefix: RetinaDepartmentTabsEnum::COLLECTIONS->value
                        )
                    )
                    : Inertia::lazy(fn () => CollectionsResource::collection(
                        IndexRetinaCollections::run(
                            parent: $department,
                            prefix: RetinaDepartmentTabsEnum::COLLECTIONS->value
                        )
                    )),

            ]
        )->table(
            IndexRetinaSubDepartments::make()->tableStructure(
                parent: $department,
                prefix: RetinaDepartmentTabsEnum::SUB_DEPARTMENTS->value
            )
        )->table(
            IndexRetinaFamilies::make()->tableStructure(
                parent: $department,
                prefix: RetinaDepartmentTabsEnum::FAMILIES->value
            )
        )->table(
            IndexRetinaProductsInCatalogue::make()->tableStructure(
                shop: $department->shop,
                prefix: RetinaDepartmentTabsEnum::PRODUCTS->value
            )
        )->table(
            IndexRetinaCollections::make()->tableStructure(
                shop: $department->shop,
                prefix: RetinaDepartmentTabsEnum::COLLECTIONS->value
            )
        );
    }


    public function jsonResponse(ProductCategory $department): DepartmentsResource
    {
        return new DepartmentsResource($department);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (ProductCategory $department, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Departments')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $department->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        $department = ProductCategory::where('slug', $routeParameters['department'])->first();

        return match ($routeName) {

            'retina.catalogue.department.show' =>
            array_merge(
                ShowRetinaCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $department,
                    [
                        'index' => [
                            'name'       => 'retina.catalogue.department.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'retina.catalogue.department.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(ProductCategory $department, ActionRequest $request): ?array
    {
        $previous = ProductCategory::where('code', '<', $department->code)->where('shop_id', $this->shop->id)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(ProductCategory $department, ActionRequest $request): ?array
    {
        $next = ProductCategory::where('code', '>', $department->code)->where('shop_id', $this->shop->id)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?ProductCategory $department, string $routeName): ?array
    {
        if (!$department) {
            return null;
        }

        return match ($routeName) {

            'retina.catalogue.department.show' => [
                'label' => $department->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'department'   => $department->slug
                    ]
                ]
            ],
        };
    }
}
