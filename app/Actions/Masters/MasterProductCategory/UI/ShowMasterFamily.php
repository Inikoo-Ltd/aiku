<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Catalogue\ProductCategory\UI\IndexFamilies;
use App\Actions\Catalogue\Shop\UI\IndexOpenShopsInMasterShop;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\Comms\Mailshot\UI\IndexMailshots;
use App\Actions\GrpAction;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Masters\MasterProductCategory\WithMasterFamilySubNavigation;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\Masters\MasterVariant\IndexMasterVariant;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\UI\SupplyChain\MasterFamilyTabsEnum;
use App\Http\Resources\Api\Dropshipping\OpenShopsInMasterShopResource;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\Catalogue\FamiliesResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Masters\MasterProductCategoryTimeSeriesResource;
use App\Http\Resources\Masters\MasterVariantsResource;
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
    use WithMasterFamilyNavigation;
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

        $tabs = [
            MasterFamilyTabsEnum::SALES->value =>
                $this->tab === MasterFamilyTabsEnum::SALES->value
                    ? fn () => MasterProductCategoryTimeSeriesResource::collection(
                        IndexMasterProductCategoryTimeSeries::run($masterFamily, MasterFamilyTabsEnum::SALES->value)
                    )
                    : Inertia::lazy(
                        fn () => MasterProductCategoryTimeSeriesResource::collection(
                            IndexMasterProductCategoryTimeSeries::run($masterFamily, MasterFamilyTabsEnum::SALES->value)
                        )
                    ),

            'salesData' => $this->tab === MasterFamilyTabsEnum::SHOWCASE->value
                ? fn () => GetMasterProductCategoryTimeSeriesData::run($masterFamily)
                : Inertia::lazy(fn () => GetMasterProductCategoryTimeSeriesData::run($masterFamily)),

            MasterFamilyTabsEnum::SHOWCASE->value =>
                $this->tab === MasterFamilyTabsEnum::SHOWCASE->value
                    ? fn () => GetMasterProductCategoryShowcase::run($masterFamily)
                    : Inertia::lazy(
                        fn () => GetMasterProductCategoryShowcase::run($masterFamily)
                    ),

            MasterFamilyTabsEnum::FAMILIES->value =>
                $this->tab === MasterFamilyTabsEnum::FAMILIES->value
                    ? fn () => FamiliesResource::collection(
                        IndexFamilies::run($masterFamily)
                    )
                    : Inertia::lazy(
                        fn () => FamiliesResource::collection(
                            IndexFamilies::run($masterFamily)
                        )
                    ),

            MasterFamilyTabsEnum::IMAGES->value =>
                $this->tab === MasterFamilyTabsEnum::IMAGES->value
                    ? fn () => GetMasterProductCategoryImages::run($masterFamily)
                    : Inertia::lazy(
                        fn () => GetMasterProductCategoryImages::run($masterFamily)
                    ),

            MasterFamilyTabsEnum::HISTORY->value =>
                $this->tab == MasterFamilyTabsEnum::HISTORY->value
                    ? fn () => HistoryResource::collection(IndexHistory::run($masterFamily, MasterFamilyTabsEnum::HISTORY->value))
                    : Inertia::lazy(
                        fn () => HistoryResource::collection(IndexHistory::run($masterFamily, MasterFamilyTabsEnum::HISTORY->value))
                    ),


        ];

        $navigation = MasterFamilyTabsEnum::navigation();
        $tabs[MasterFamilyTabsEnum::VARIANTS->value] =
            $this->tab === MasterFamilyTabsEnum::VARIANTS->value
                ? fn () => MasterVariantsResource::collection(
                    IndexMasterVariant::run(
                        $masterFamily,
                        MasterFamilyTabsEnum::VARIANTS->value
                    )
                )
                : Inertia::lazy(
                    fn () => MasterVariantsResource::collection(
                        IndexMasterVariant::run(
                            $masterFamily,
                            MasterFamilyTabsEnum::VARIANTS->value
                        )
                    )
                );


        $stateIcon= $masterFamily->status
            ? [
                'tooltip' => __('Active'),
                'icon'    => 'fas fa-check-circle',
                'class'   => 'text-green-400'
            ]
            : [
                'tooltip' => __('Closed'),
                'icon'    => 'fas fa-times-circle',
                'class'   => 'text-red-400'
            ];


        if($masterFamily->stats->number_master_assets==0 && $masterFamily->stats->number_families==0 ){
            $stateIcon=[
                'tooltip' => __('Empty'),
                'icon'    => 'fas fa-cactus',
                'class'   => 'text-yellow-400'
            ];
        }


        return Inertia::render(
            'Masters/MasterFamily',
            [
                'title'                   => __('Master family').': '.$masterFamily->code,
                'breadcrumbs'             => $this->getBreadcrumbs(
                    $masterFamily,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'              => [
                    'previous' => $this->getPreviousModel($masterFamily, $request),
                    'next'     => $this->getNextModel($masterFamily, $request),
                ],
                'mini_breadcrumbs'        => array_filter(
                    [
                        $masterFamily->master_department_id ? [
                            'label'   => $masterFamily->masterDepartment->name,
                            'to'      => [
                                'name'       => 'grp.masters.master_shops.show.master_departments.show',
                                'parameters' => [
                                    'masterShop'       => $masterFamily->masterShop->slug,
                                    'masterDepartment' => $masterFamily->masterDepartment->slug
                                ]
                            ],
                            'tooltip' => 'Master Department',
                            'icon'    => ['fal', 'folder-tree']
                        ] : [],
                        $masterFamily->master_sub_department_id ? [
                            'label'   => $masterFamily->masterSubDepartment->code,
                            'to'      => [
                                'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.show',
                                'parameters' => [
                                    'masterShop'          => $masterFamily->masterShop->slug,
                                    'masterDepartment'    => $masterFamily->masterDepartment->slug,
                                    'masterSubDepartment' => $masterFamily->masterSubDepartment->slug
                                ]
                            ],
                            'tooltip' => __('Master Sub-Department'),
                            'icon'    => ['fal', 'folder-tree']
                        ] : [],
                        [
                            'label'   => $masterFamily->name,
                            'tooltip' => 'Master Family',
                            'icon'    => ['fal', 'folder-tree']
                        ]
                    ],
                ),
                'familyId'                => $masterFamily->id,
                'currency'                => $masterFamily->group->currency,
                'storeProductRoute'       => [
                    'name'       => 'grp.models.master_family.store-assets',
                    'parameters' => [
                        'masterFamily' => $masterFamily->id,
                    ]
                ],
                'pageHead'                => [
                    'title'         => $masterFamily->name,
                    'model'         => __('Master Family'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-folder'],
                        'title' => __('Department')
                    ],
                    'iconRight'     =>$stateIcon,
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
                        $this->canEdit
                            ? [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('Add a master product to this family'),
                                'label'   => __('Master Product'),
                            ]
                            : false,
                        $this->canEdit && $masterFamily->masterShop->type->value  != 'dropshipping' ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'key'     => 'variants',
                            'tooltip' => __('Create a variants group for this family'),
                            'label'   => __('Variants'),
                            'route'   => [
                                'name'       => preg_replace('/show$/', 'master_variants.create', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,
                    ],
                    'subNavigation' => $this->getMasterFamilySubNavigation($masterFamily)

                ],
                'tabs'                    => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],
                'isPerfectFamily'         => true,
                'masterProductCategoryId' => $masterFamily->id,
                'shopsData'               => OpenShopsInMasterShopResource::collection(IndexOpenShopsInMasterShop::run($masterFamily->masterShop, 'shops')),
                ...$tabs,


            ]
        )
            ->table(IndexMailshots::make()->tableStructure(parent: $masterFamily))
            ->table(IndexFamilies::make()->tableStructure(parent: $masterFamily, prefix: MasterFamilyTabsEnum::FAMILIES->value, sales: false))
            ->table(IndexMasterProductCategoryTimeSeries::make()->tableStructure(MasterFamilyTabsEnum::SALES->value))
            ->table(IndexMasterVariant::make()->tableStructure(parent: $masterFamily, prefix: MasterFamilyTabsEnum::VARIANTS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: MasterFamilyTabsEnum::HISTORY->value));

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
                ShowMasterShop::make()->getBreadcrumbs($masterFamily->masterShop),
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

}
