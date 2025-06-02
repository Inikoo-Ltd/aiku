<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:35:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\WithSubDepartmentSubNavigation;
use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\Catalogue\DepartmentTabsEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\CRM\CustomersResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSubDepartment extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithSubDepartmentSubNavigation;
    use WithWebpageActions;


    private Organisation|Shop|ProductCategory $parent;

    public function handle(ProductCategory $subDepartment): ProductCategory
    {
        return $subDepartment;
    }


    public function asController(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ActionRequest $request): ProductCategory
    {
        $this->parent = $department;
        $this->initialisationFromShop($shop, $request)->withTab(DepartmentTabsEnum::values());

        return $this->handle($subDepartment);
    }

    public function htmlResponse(ProductCategory $subDepartment, ActionRequest $request): Response
    {
        $parentTag = [
                    [
                        'label' => $subDepartment->department->name,
                        'route' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show',
                            'parameters' => $request->route()->originalParameters()
                        ],
                        'icon'  => 'fal fa-folder-tree'
                    ]
                ];
        return Inertia::render(
            'Org/Catalogue/SubDepartment',
            [
                'title'       => __('Sub-department'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $subDepartment,
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($subDepartment, $request),
                    'next'     => $this->getNext($subDepartment, $request),
                ],
                'pageHead'    => [
                    'title'         => $subDepartment->name,
                    'model'         => __('Sub-department'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-folder-tree'],
                        'title' => __('Sub-department')
                    ],
                    'actions'       => [
                        $this->getWebpageActions($subDepartment),
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,
                        $this->canDelete ? [
                            'type'  => 'button',
                            'style' => 'delete',
                            'route' => [
                                'name'       => 'shops.show.departments.remove',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false
                    ],
                    'parentTag' => $parentTag,
                    'subNavigation' => $this->getSubDepartmentSubNavigation($subDepartment)
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => DepartmentTabsEnum::navigation()
                ],

                'routes'    => [
                    'attach_families' => [
                        'name'       => 'grp.models.sub-department.families.attach',
                        'parameters' => [
                            'subDepartment' => $subDepartment->slug,
                        ],
                        'method'     => 'post'
                    ],
                    'fetch_families' => [
                        'name'       => 'grp.json.shop.catalogue.departments.families',
                        'parameters' => [
                            'shop'  => $subDepartment->shop->slug,
                            'productCategory'   => $this->parent->slug
                        ]
                    ],
                ],

                DepartmentTabsEnum::SHOWCASE->value => $this->tab == DepartmentTabsEnum::SHOWCASE->value ?
                    fn () => GetProductCategoryShowcase::run($subDepartment)
                    : Inertia::lazy(fn () => GetProductCategoryShowcase::run($subDepartment)),

                DepartmentTabsEnum::CUSTOMERS->value => $this->tab == DepartmentTabsEnum::CUSTOMERS->value
                    ?
                    fn () => CustomersResource::collection(
                        IndexCustomers::run(
                            parent: $subDepartment->shop,
                            prefix: 'customers'
                        )
                    )
                    : Inertia::lazy(fn () => CustomersResource::collection(
                        IndexCustomers::run(
                            parent: $subDepartment->shop,
                            prefix: 'customers'
                        )
                    )),


                DepartmentTabsEnum::HISTORY->value => $this->tab == DepartmentTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($subDepartment))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($subDepartment))),


            ]
        )->table(
            IndexCustomers::make()->tableStructure(
                parent: $subDepartment->shop,
                prefix: 'customers'
            )
        )
            ->table(IndexHistory::make()->tableStructure(prefix: DepartmentTabsEnum::HISTORY->value));
    }


    public function jsonResponse(ProductCategory $subDepartment): DepartmentsResource
    {
        return new DepartmentsResource($subDepartment);
    }


    public function getBreadcrumbs(ProductCategory $subDepartment, array $routeParameters, ?string $suffix = null): array
    {
        return
            array_merge(
                ShowDepartment::make()->getBreadcrumbs('grp.org.shops.show.catalogue.departments.show', Arr::only($routeParameters, ['organisation', 'shop', 'department'])),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route'  => [
                                'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show',
                                'parameters' => $routeParameters
                            ],
                            'label'  => $subDepartment->name,
                            'suffix' => $suffix,
                        ]
                    ]
                ]
            );
    }

    public function getPrevious(ProductCategory $subDepartment, ActionRequest $request): ?array
    {
        $previous = ProductCategory::where('code', '<', $subDepartment->code)->where('shop_id', $subDepartment->shop->id)
            ->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
            ->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(ProductCategory $subDepartment, ActionRequest $request): ?array
    {
        $next = ProductCategory::where('code', '>', $subDepartment->code)->where('shop_id', $subDepartment->shop->id)
            ->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
            ->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?ProductCategory $subDepartment, string $routeName): ?array
    {
        if (!$subDepartment) {
            return null;
        }


        return [
            'label' => $subDepartment->name,
            'route' => [
                'name'       => $routeName,
                'parameters' => [
                    'organisation'  => $subDepartment->organisation->slug,
                    'shop'          => $subDepartment->shop->slug,
                    'department'    => $subDepartment->parent->slug,
                    'subDepartment' => $subDepartment->slug
                ]
            ]
        ];
    }
}
