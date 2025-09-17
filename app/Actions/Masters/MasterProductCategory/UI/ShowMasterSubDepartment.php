<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Catalogue\ProductCategory\UI\IndexSubDepartments;
use App\Actions\GrpAction;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Masters\MasterProductCategory\WithMasterSubDepartmentSubNavigation;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\UI\SupplyChain\MasterSubDepartmentTabsEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Api\Dropshipping\OpenShopsInMasterShopResource;
use App\Actions\Catalogue\Shop\UI\IndexOpenShopsInMasterShop;
use App\Http\Resources\Catalogue\SubDepartmentsResource;

class ShowMasterSubDepartment extends GrpAction
{
    use WithMasterSubDepartmentSubNavigation;
    use WithMastersAuthorisation;


    private MasterShop|MasterProductCategory $parent;

    public function handle(MasterProductCategory $masterSubDepartment): MasterProductCategory
    {
        return $masterSubDepartment;
    }


    public function asController(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterShop;
        $group        = group();
        $this->initialisation($group, $request)->withTab(MasterSubDepartmentTabsEnum::values());

        return $this->handle($masterSubDepartment);
    }

    public function inMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterDepartment;
        $group        = group();
        $this->initialisation($group, $request)->withTab(MasterSubDepartmentTabsEnum::values());

        return $this->handle($masterSubDepartment);
    }

    public function htmlResponse(MasterProductCategory $masterSubDepartment, ActionRequest $request): Response
    {
        $subNavigation = $this->getMasterSubDepartmentSubNavigation($masterSubDepartment);

        return Inertia::render(
            'Masters/MasterSubDepartment',
            [
                'title'       => __('master sub-department'),
                 'breadcrumbs' => $this->getBreadcrumbs(
                     $masterSubDepartment,
                     $request->route()->getName(),
                     $request->route()->originalParameters()
                 ),
                'navigation'  => [
                    'previous' => $this->getPrevious($masterSubDepartment, $request),
                    'next'     => $this->getNext($masterSubDepartment, $request),
                ],
                'mini_breadcrumbs' => array_filter(
                    [
                        [
                            'label' => $masterSubDepartment->masterDepartment->name,
                            'to'    => [
                                'name'       => 'grp.masters.master_shops.show.master_departments.show',
                                'parameters' => [
                                    'masterShop'         => $masterSubDepartment->masterShop->slug,
                                    'masterDepartment'   => $masterSubDepartment->masterDepartment->slug
                                ]
                            ],
                            'tooltip' => 'Master Department',
                            'icon' => ['fal', 'folder-tree']
                        ],
                        [
                            'label' => $masterSubDepartment->name,
                            'to'    => [
                                'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.show',
                                'parameters' => [
                                    'masterShop'         => $masterSubDepartment->masterShop->slug,
                                    'masterDepartment'   => $masterSubDepartment->masterDepartment->slug,
                                    'masterSubDepartment' => $masterSubDepartment->slug
                                ]
                            ],
                            'tooltip' => 'Master Sub-Departement',
                            'icon' => ['fal', 'folder-tree']
                        ],
                    ],
                ),
                'pageHead'    => [
                    'title'   => $masterSubDepartment->name,
                    'model'   => __('master sub-department'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-folder-tree'],
                        'title' => __('master sub-department')
                    ],
                    'actions' => [
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
                    'subNavigation' => $subNavigation,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => MasterSubDepartmentTabsEnum::navigation()
                ],

                'routes' => [
                    'fetch_families' => [
                        'name'       => 'grp.json.master_product_category.families.index',
                        'parameters' => [
                            'masterProductCategory' => $masterSubDepartment->slug
                        ]
                    ],
                    'attach_families' => [
                        'name'       => 'grp.models.master-sub-department.families.attach',
                        'parameters' => [
                            'masterSubDepartment' => $masterSubDepartment->id
                        ]
                    ],
                    'detach_families' => [
                        'name'       => 'grp.models.master-sub-department.family.detach',
                        'parameters' => [
                            'masterSubDepartment' => $masterSubDepartment->id
                        ]
                    ]
                ],
                'storeRoute' =>  match ($this->parent::class) {
                    MasterShop::class => [
                        'name' => 'grp.models.master_shops.master_family.store',
                        'parameters' => [
                            'masterShop' => $this->parent->id
                        ]
                    ],
                    MasterProductCategory::class => [
                        'name' => 'grp.models.master-sub-department.master_family.store',
                            'parameters' => [
                                'masterSubDepartment' => $this->parent->id
                        ]
                    ],
                    default => []
                },
                'shopsData' => OpenShopsInMasterShopResource::collection(IndexOpenShopsInMasterShop::run($masterSubDepartment->masterShop, 'shops')),

                MasterSubDepartmentTabsEnum::SHOWCASE->value => $this->tab == MasterSubDepartmentTabsEnum::SHOWCASE->value ?
                    fn () => GetMasterProductCategoryShowcase::run($masterSubDepartment)
                    : Inertia::lazy(fn () => GetMasterProductCategoryShowcase::run($masterSubDepartment)),

                MasterSubDepartmentTabsEnum::SUB_DEPARTMENTS->value => $this->tab == MasterSubDepartmentTabsEnum::SUB_DEPARTMENTS->value ?
                    fn () => SubDepartmentsResource::collection(IndexSubDepartments::run($masterSubDepartment))
                    : Inertia::lazy(fn () => SubDepartmentsResource::collection(IndexSubDepartments::run($masterSubDepartment))),

                MasterSubDepartmentTabsEnum::HISTORY->value => $this->tab == MasterSubDepartmentTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($masterSubDepartment))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($masterSubDepartment))),

                MasterSubDepartmentTabsEnum::IMAGES->value => $this->tab == MasterSubDepartmentTabsEnum::IMAGES->value ?
                    fn () =>  GetMasterProductCategoryImages::run($masterSubDepartment)
                    : Inertia::lazy(fn () => GetMasterProductCategoryImages::run($masterSubDepartment)),


            ]
        )
            ->table(IndexSubDepartments::make()->tableStructure(parent: $masterSubDepartment, prefix: MasterSubDepartmentTabsEnum::SUB_DEPARTMENTS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: MasterSubDepartmentTabsEnum::HISTORY->value));
    }


    public function jsonResponse(MasterProductCategory $masterSubDepartment): DepartmentsResource
    {
        return new DepartmentsResource($masterSubDepartment);
    }

    public function getBreadcrumbs(MasterProductCategory $masterSubDepartment, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (MasterProductCategory $masterSubDepartment, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('master sub-departments')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $masterSubDepartment->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        return match ($routeName) {
            'grp.masters.master_departments.show.master_sub_departments.show',
            'grp.masters.master_departments.show.master_sub_departments.show.master_families.index' =>
            array_merge(
                (new IndexMasterSubDepartments())->getBreadcrumbs($masterSubDepartment, $masterSubDepartment->masterDepartment, $routeParameters, $suffix),
                $headCrumb(
                    $masterSubDepartment,
                    [
                        'index' => [
                            'name'       => 'grp.masters.master_departments.show.master_sub_departments.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.masters.master_departments.show.master_sub_departments.show',
                            'parameters' => $routeParameters


                        ]
                    ],
                    $suffix
                )
            ),
            'grp.masters.master_shops.show.master_sub_departments.show',
            'grp.masters.master_shops.show.master_sub_departments.master_families.index',
            'grp.masters.master_shops.show.master_sub_departments.master_collections.index',
            'grp.masters.master_shops.show.master_sub_departments.edit',
            'grp.masters.master_shops.show.master_sub_departments.master_families.show',
            'grp.masters.master_shops.show.master_sub_departments.master_families.master_products.index' =>
            array_merge(
                (new ShowMasterShop())->getBreadcrumbs($masterSubDepartment->masterShop, $routeName, $suffix),
                $headCrumb(
                    $masterSubDepartment,
                    [
                        'index' => [
                            'name'       => 'grp.masters.master_shops.show.master_sub_departments.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.masters.master_shops.show.master_sub_departments.show',
                            'parameters' => $routeParameters


                        ]
                    ],
                    $suffix
                )
            ),
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.show',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.index',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.show',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.master_products.index',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.edit' =>
            array_merge(
                ShowMasterDepartment::make()->getBreadcrumbs($masterSubDepartment->masterShop, $masterSubDepartment->masterDepartment, $routeName, $routeParameters, $suffix),
                $headCrumb(
                    $masterSubDepartment,
                    [
                        'index' => [
                            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.show',
                            'parameters' => $routeParameters


                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(MasterProductCategory $masterSubDepartment, ActionRequest $request): ?array
    {
        $previous = MasterProductCategory::where('code', '<', $masterSubDepartment->code)->orderBy('code', 'desc')->where('master_shop_id', $this->parent->id)->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(MasterProductCategory $masterSubDepartment, ActionRequest $request): ?array
    {
        $next = MasterProductCategory::where('code', '>', $masterSubDepartment->code)->orderBy('code')->where('master_shop_id', $this->parent->id)->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?MasterProductCategory $masterSubDepartment, string $routeName): ?array
    {
        if (!$masterSubDepartment) {
            return null;
        }

        return match ($routeName) {
            'grp.masters.master_shops.show.master_sub_departments.show' => [
                'label' => $masterSubDepartment->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'masterShop'          => $masterSubDepartment->masterShop->slug,
                        'masterSubDepartment' => $masterSubDepartment->slug
                    ]
                ]
            ],
            default => [] // Add a default case to handle unmatched route names
        };
    }
}
