<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:35:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\Comms\Mailshot\UI\IndexMailshots;
use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\Catalogue\FamilyTabsEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\CRM\CustomersResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowFamily extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithFamilySubNavigation;
    use WithWebpageActions;

    private Organisation|ProductCategory|Shop $parent;

    public function handle(ProductCategory $family): ProductCategory
    {
        return $family;
    }


    public function asController(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $family, ActionRequest $request): ProductCategory
    {
        $this->parent = $department;

        $this->initialisationFromShop($shop, $request)->withTab(FamilyTabsEnum::values());

        return $this->handle($family);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ProductCategory $family, ActionRequest $request): ProductCategory
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(FamilyTabsEnum::values());

        return $this->handle($family);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ProductCategory $family, ActionRequest $request): ProductCategory
    {
        $this->parent = $subDepartment;

        $this->initialisationFromShop($shop, $request)->withTab(FamilyTabsEnum::values());

        return $this->handle($family);
    }

    public function getActions(ProductCategory $family, ActionRequest $request): array
    {
        $actions = [];

        return array_merge($actions, [
            $this->getWebpageActions($family),
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
                    'name'       => 'shops.show.families.remove',
                    'parameters' => $request->route()->originalParameters()
                ]
            ] : false
        ]);
    }


    public function htmlResponse(ProductCategory $family, ActionRequest $request): Response
    {
        $parentTag = [];

        if ($this->parent instanceof ProductCategory) {
            if ($this->parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
                $parentTag = [
                        [
                            'label' => $family->department->name,
                            'route' => [
                                'name'       => 'grp.org.shops.show.catalogue.departments.index',
                                'parameters' => $request->route()->originalParameters()
                            ],
                            'icon'  => 'fal fa-folder-tree'
                        ]
                        ];
            } elseif ($this->parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $parentTag = [
                    [
                        'label' => $family->subDepartment->name,
                        'route' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show',
                            'parameters' => $request->route()->originalParameters()
                        ],
                        'icon'  => 'fal fa-folder-tree'
                    ]
                ];
            }
        }

        return Inertia::render(
            'Org/Catalogue/Family',
            [
                'title'       => __('family'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $family,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($family, $request),
                    'next'     => $this->getNext($family, $request),
                ],
                'pageHead'    => [
                    'title'   => $family->name,
                    'model'   => __('Family'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-folder'],
                        'title' => __('department')
                    ],
                    'actions' => $this->getActions($family, $request),
                    'parentTag' => $parentTag,

                    'subNavigation' => $this->getFamilySubNavigation($family, $this->parent, $request)

                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => FamilyTabsEnum::navigation()
                ],

                FamilyTabsEnum::SHOWCASE->value => $this->tab == FamilyTabsEnum::SHOWCASE->value ?
                    fn () => GetProductCategoryShowcase::run($family)
                    : Inertia::lazy(fn () => GetProductCategoryShowcase::run($family)),

                FamilyTabsEnum::CUSTOMERS->value => $this->tab == FamilyTabsEnum::CUSTOMERS->value ?
                    fn () => CustomersResource::collection(IndexCustomers::run(parent: $family->shop, prefix: FamilyTabsEnum::CUSTOMERS->value))
                    : Inertia::lazy(fn () => CustomersResource::collection(IndexCustomers::run(parent: $family->shop, prefix: FamilyTabsEnum::CUSTOMERS->value))),


            ]
        )->table(IndexCustomers::make()->tableStructure(parent: $family->shop, prefix: FamilyTabsEnum::CUSTOMERS->value))
            ->table(IndexMailshots::make()->tableStructure($family));
    }


    public function jsonResponse(ProductCategory $family): DepartmentsResource
    {
        return new DepartmentsResource($family);
    }

    public function getBreadcrumbs(ProductCategory $family, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (ProductCategory $family, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Families')
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


        return match ($routeName) {
            'grp.org.shops.show.catalogue.families.show' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $family,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.families.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.families.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.families.show' =>
            array_merge(
                (new ShowDepartment())->getBreadcrumbs('grp.org.shops.show.catalogue.departments.show', $routeParameters),
                $headCrumb(
                    $family,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.families.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.families.show',
                            'parameters' => $routeParameters


                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show' =>
            array_merge(
                (new ShowSubDepartment())->getBreadcrumbs(
                    $family->parent,
                    $routeParameters
                ),
                $headCrumb(
                    $family,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show',
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
        $previous = ProductCategory::where('code', '<', $family->code)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(ProductCategory $family, ActionRequest $request): ?array
    {
        $next = ProductCategory::where('code', '>', $family->code)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?ProductCategory $family, string $routeName): ?array
    {
        if (!$family) {
            return null;
        }

        return match ($routeName) {
            'shops.families.show' => [
                'label' => $family->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'department' => $family->slug
                    ]
                ]
            ],
            'shops.show.families.show' => [
                'label' => $family->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'shop'       => $family->shop->slug,
                        'department' => $family->slug
                    ]
                ]
            ],
            default => [] // Add a default case to handle unmatched route names
        };
    }
}
