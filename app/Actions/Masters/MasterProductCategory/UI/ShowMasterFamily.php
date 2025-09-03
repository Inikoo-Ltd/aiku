<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\IndexOpenShopsInMasterShop;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\Comms\Mailshot\UI\IndexMailshots;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\WithMasterFamilySubNavigation;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\UI\SupplyChain\MasterFamilyTabsEnum;
use App\Http\Resources\Api\Dropshipping\OpenShopsInMasterShopResource;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMasterFamily extends GrpAction
{
    use WithFamilySubNavigation;
    use WithMastersAuthorisation;
    use WithMasterFamilySubNavigation;

    private MasterShop|Group|MasterProductCategory $parent;

    public function handle(MasterProductCategory $masterFamily): MasterProductCategory
    {
        return $masterFamily;
    }


    public function asController(MasterShop $masterShop, MasterProductCategory $masterFamily, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterShop;
        $group        = group();

        $this->initialisation($group, $request)->withTab(MasterFamilyTabsEnum::values());

        return $this->handle($masterFamily);
    }

    public function inGroup(MasterProductCategory $masterFamily, ActionRequest $request): MasterProductCategory
    {
        $group        = group();
        $this->parent = $group;
        $this->initialisation($group, $request)->withTab(MasterFamilyTabsEnum::values());

        return $this->handle($masterFamily);
    }

    public function inMasterDepartment(MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, ActionRequest $request): MasterProductCategory
    {
        $group        = group();
        $this->parent = $masterDepartment;
        $this->initialisation($group, $request)->withTab(MasterFamilyTabsEnum::values());

        return $this->handle($masterFamily);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, ActionRequest $request): MasterProductCategory
    {
        $group        = group();
        $this->parent = $masterDepartment;
        $this->initialisation($group, $request)->withTab(MasterFamilyTabsEnum::values());

        return $this->handle($masterFamily);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartment(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): MasterProductCategory
    {
        $group        = group();
        $this->parent = $masterSubDepartment;
        $this->initialisation($group, $request)->withTab(MasterFamilyTabsEnum::values());

        return $this->handle($masterFamily);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartmentInMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): MasterProductCategory
    {
        $group        = group();
        $this->parent = $masterSubDepartment;
        $this->initialisation($group, $request)->withTab(MasterFamilyTabsEnum::values());

        return $this->handle($masterFamily);
    }

    public function htmlResponse(MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Catalogue/Family',
            [
                'title'       => __('family'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $masterFamily,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($masterFamily, $request),
                    'next'     => $this->getNext($masterFamily, $request),
                ],
                'familyId'      => $masterFamily->id,
                'currency' => $masterFamily->group->currency,
                'storeProductRoute' => [
                        'name'       => 'grp.models.master_family.store-assets',
                        'parameters' => [
                            'masterFamily' => $masterFamily->id,
                        ]
                ],
                'pageHead'    => [
                    'title'         => $masterFamily->name,
                    'model'         => __('Master Family'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-folder'],
                        'title' => __('department')
                    ],
                    'actions'       => [
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
                        ] : false,
                        [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('Add a master product to this family'),
                            'label'   => __('master product'),
                        ],
                    ],
                    'subNavigation' => $this->getMasterFamilySubNavigation($masterFamily)

                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => MasterFamilyTabsEnum::navigation()
                ],

                'shopsData' => OpenShopsInMasterShopResource::collection(IndexOpenShopsInMasterShop::run($masterFamily->masterShop, 'shops')),

                MasterFamilyTabsEnum::SHOWCASE->value => $this->tab == MasterFamilyTabsEnum::SHOWCASE->value ?
             fn () => GetMasterProductCategoryShowcase::run($masterFamily)
             : Inertia::lazy(fn () => GetMasterProductCategoryShowcase::run($masterFamily)),

                // FamilyTabsEnum::CUSTOMERS->value => $this->tab == FamilyTabsEnum::CUSTOMERS->value ?
                //     fn () => CustomersResource::collection(IndexCustomers::run(parent : $masterFamily->shop, prefix: FamilyTabsEnum::CUSTOMERS->value))
                //     : Inertia::lazy(fn () => CustomersResource::collection(IndexCustomers::run(parent : $masterFamily->shop, prefix: FamilyTabsEnum::CUSTOMERS->value))),
                // FamilyTabsEnum::MAILSHOTS->value => $this->tab == FamilyTabsEnum::MAILSHOTS->value ?
                //     fn () => MailshotResource::collection(IndexMailshots::run($masterFamily))
                //     : Inertia::lazy(fn () => MailshotResource::collection(IndexMailshots::run($masterFamily))),


            ]
        )
            // ->table(IndexCustomers::make()->tableStructure(parent: $masterFamily->shop, prefix: FamilyTabsEnum::CUSTOMERS->value))
            ->table(IndexMailshots::make()->tableStructure($masterFamily));
    }


    public function jsonResponse(MasterProductCategory $masterFamily): DepartmentsResource
    {
        return new DepartmentsResource($masterFamily);
    }

    public function getBreadcrumbs(MasterProductCategory $masterFamily, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (MasterProductCategory $masterFamily, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Master families')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $masterFamily->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };


        return match ($routeName) {
            'grp.masters.master_shops.show.master_families.show',
            'grp.masters.master_shops.show.master_families.edit',
            'grp.masters.master_shops.show.master_families.create',
            'grp.masters.master_shops.show.master_families.master_products.index',
            'grp.masters.master_shops.show.master_families.master_products.show' =>
            array_merge(
                ShowMasterShop::make()->getBreadcrumbs($masterFamily->masterShop, $routeParameters),
                $headCrumb(
                    $masterFamily,
                    [
                        'index' => [
                            'name'       => 'grp.masters.master_shops.show.master_families.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.masters.master_shops.show.master_families.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.masters.master_departments.show.master_families.show' =>
            array_merge(
                (new ShowMasterDepartment())->getBreadcrumbs($masterFamily, $masterFamily->masterDepartment, $routeName, $routeParameters),
                $headCrumb(
                    $masterFamily,
                    [
                        'index' => [
                            'name'       => 'grp.masters.master_departments.show.master_families.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.masters.master_departments.show.master_families.show',
                            'parameters' => $routeParameters


                        ]
                    ],
                    $suffix
                )
            ),
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.show',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.master_products.index' =>
            array_merge(
                (new ShowMasterSubDepartment())->getBreadcrumbs($masterFamily->parent, $routeName, $routeParameters),
                $headCrumb(
                    $masterFamily,
                    [
                        'index' => [
                            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.show',
                            'parameters' => $routeParameters


                        ]
                    ],
                    $suffix
                )
            ),
            'grp.masters.master_shops.show.master_departments.show.master_families.show',
            'grp.masters.master_shops.show.master_departments.show.master_families.show.master_products.index' =>
            array_merge(
                ShowMasterDepartment::make()->getBreadcrumbs($masterFamily->masterShop, $masterFamily->masterDepartment, $routeName, $routeParameters, $suffix),
                $headCrumb(
                    $masterFamily,
                    [
                        'index' => [
                            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_families.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_families.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.masters.master_shops.show.master_sub_departments.master_families.show',
            'grp.masters.master_shops.show.master_sub_departments.master_families.master_products.index' =>
            array_merge(
                ShowMasterSubDepartment::make()->getBreadcrumbs($masterFamily->masterSubDepartment, $routeName, $routeParameters, $suffix),
                $headCrumb(
                    $masterFamily,
                    [
                        'index' => [
                            'name'       => 'grp.masters.master_shops.show.master_sub_departments.master_families.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.masters.master_shops.show.master_sub_departments.master_families.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(MasterProductCategory $masterFamily, ActionRequest $request): ?array
    {
        $previous = MasterProductCategory::where('code', '<', $masterFamily->code)->orderBy('code', 'desc')->where('master_shop_id', $this->parent->id)->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(MasterProductCategory $masterFamily, ActionRequest $request): ?array
    {
        $next = MasterProductCategory::where('code', '>', $masterFamily->code)->orderBy('code')->where('master_shop_id', $this->parent->id)->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?MasterProductCategory $masterFamily, string $routeName): ?array
    {
        if (!$masterFamily) {
            return null;
        }

        return match ($routeName) {
            'grp.masters.master_families.show' => [
                'label' => $masterFamily->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'masterFamily' => $masterFamily->slug
                    ]
                ]
            ],
            'grp.masters.master_shops.show.master_families.show' => [
                'label' => $masterFamily->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'masterShop'   => $masterFamily->masterShop->slug,
                        'masterFamily' => $masterFamily->slug
                    ]
                ]
            ],
            default => []
        };
    }
}
