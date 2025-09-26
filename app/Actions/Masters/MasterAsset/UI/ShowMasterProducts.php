<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Catalogue\Product\UI\IndexProductsInMasterProduct;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\Comms\Mailshot\UI\IndexMailshots;
use App\Actions\Goods\TradeUnit\UI\IndexTradeUnitsInMasterProduct;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterDepartment;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterFamily;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterSubDepartment;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\UI\SupplyChain\MasterAssetTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Http\Resources\Goods\TradeUnitsResource;
use App\Http\Resources\Masters\MasterProductResource;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMasterProducts extends GrpAction
{
    use WithFamilySubNavigation;
    use WithMastersAuthorisation;

    private MasterShop|Group|MasterAsset|MasterProductCategory $parent;

    public function handle(MasterAsset $masterAsset): MasterAsset
    {
        return $masterAsset;
    }

    public function asController(MasterShop $masterShop, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->parent = $masterShop;
        $group = group();

        $this->initialisation($group, $request)->withTab(MasterAssetTabsEnum::values());

        return $this->handle($masterProduct);
    }

    public function inGroup(MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $group        = group();
        $this->parent = $group;
        $this->initialisation($group, $request)->withTab(MasterAssetTabsEnum::values());

        return $this->handle($masterProduct);
    }

    public function inMasterDepartment(MasterAsset $masterDepartment, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $group        = group();
        $this->parent = $masterDepartment;
        $this->initialisation($group, $request)->withTab(MasterAssetTabsEnum::values());

        return $this->handle($masterProduct);
    }

    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->parent = $masterDepartment;
        $this->initialisation($masterShop->group, $request)->withTab(MasterAssetTabsEnum::values());

        return $this->handle($masterProduct);
    }

    public function inMasterFamilyInMasterShop(MasterShop $masterShop, MasterProductCategory $masterFamily, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $group        = group();

        $this->parent = $masterFamily;
        $this->initialisation($group, $request)->withTab(MasterAssetTabsEnum::values());
        return $this->handle($masterProduct);
    }

    public function htmlResponse(MasterAsset $masterAsset, ActionRequest $request): Response
    {
        return Inertia::render(
            'Masters/MasterProduct',
            [
                'title'       => __('product'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $masterAsset,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($masterAsset, $request),
                    'next'     => $this->getNext($masterAsset, $request),
                ],
                'mini_breadcrumbs' => array_filter(
                    [
                        $masterAsset->master_family_id ? [
                            'label' => $masterAsset->masterDepartment ?  $masterAsset->masterDepartment->name : 'department',
                            'to'    => [
                                'name'       => 'grp.masters.master_shops.show.master_departments.show',
                                'parameters' => [
                                    'masterShop'         => $masterAsset->masterShop->slug,
                                    'masterDepartment'   => $masterAsset->masterDepartment->slug
                                ]
                            ],
                            'tooltip' => 'Master Department',
                            'icon' => ['fal', 'folder-tree']
                        ] : [],
                        $masterAsset->master_sub_department_id ? [
                            'label' => $masterAsset->masterSubDepartment ? $masterAsset->masterSubDepartment->name : 'sub department',
                            'to'    => [
                                'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.show',
                                'parameters' => [
                                    'masterShop'         => $masterAsset->masterShop->slug,
                                    'masterDepartment'   => $masterAsset->masterDepartment->slug,
                                    'masterSubDepartment' => $masterAsset->masterSubDepartment->slug
                                ]
                            ],
                            'tooltip' => __('Master Sub-Department'),
                            'icon' => ['fal', 'folder-tree']
                        ] : [],
                        $masterAsset->master_family_id ? [
                            'label' => $masterAsset->masterFamily ?  $masterAsset->masterFamily->name : 'family',
                            'to'    => [
                                'name'       => $masterAsset->master_sub_department_id ? 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.show' : 'grp.masters.master_shops.show.master_departments.show.master_families.show',
                                'parameters' =>  $masterAsset->master_sub_department_id ? [
                                    'masterShop'         => $masterAsset->masterShop->slug,
                                    'masterDepartment'   => $masterAsset->masterDepartment->slug,
                                    'masterSubDepartment'   => $masterAsset->masterSubDepartment->slug,
                                    'masterFamily' => $masterAsset->masterFamily->slug,
                                ] : [
                                    'masterShop'         => $masterAsset->masterShop->slug,
                                    'masterDepartment'   => $masterAsset->masterDepartment->slug,
                                    'masterFamily' => $masterAsset->masterFamily->slug,
                                ]
                            ],
                            'tooltip' => 'Master Family',
                            'icon' => ['fal', 'folder-tree']
                        ] : [],
                        [
                            'label' => $masterAsset->masterFamily ? $masterAsset->masterFamily->name : 'family',
                            'to'    => [
                                'name'       => 'grp.masters.master_shops.show.master_products.show',
                                'parameters' => [
                                    'masterShop'         => $masterAsset->masterShop->slug,
                                    'masterProduct' => $masterAsset->slug,
                                ]
                            ],
                            'tooltip' => 'Master Product',
                            'icon' => ['fal', 'folder-tree']
                        ],
                    ],
                ),
                'pageHead'    => [
                    'title'   => $masterAsset->code,
                    'afterTitle' => [
                        'label' => $masterAsset->name
                    ],
                    'model'   => '',
                    'icon'    => [
                        'icon'  => ['fal', 'fa-cube'],
                        'title' => __('master asset')
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ],
                        $this->canDelete ? [
                            'type'  => 'button',
                            'style' => 'delete',
                            'route' => [
                                'name'       => 'shops.show.assets.remove',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => MasterAssetTabsEnum::navigation()
                ],

                MasterAssetTabsEnum::SHOWCASE->value => $this->tab == MasterAssetTabsEnum::SHOWCASE->value ?
                    fn () => GetMasterProductShowcase::run($masterAsset)
                    : Inertia::lazy(fn () => GetMasterProductShowcase::run($masterAsset)),

                MasterAssetTabsEnum::TRADE_UNITS->value => $this->tab == MasterAssetTabsEnum::TRADE_UNITS->value ?
                    fn () => TradeUnitsResource::collection(IndexTradeUnitsInMasterProduct::run($masterAsset))
                    : Inertia::lazy(fn () => TradeUnitsResource::collection(IndexTradeUnitsInMasterProduct::run($masterAsset))),

                MasterAssetTabsEnum::IMAGES->value => $this->tab == MasterAssetTabsEnum::IMAGES->value ?
                    fn () =>  GetMasterProductImages::run($masterAsset)
                    : Inertia::lazy(fn () => GetMasterProductImages::run($masterAsset)),

                MasterAssetTabsEnum::PRODUCTS->value => $this->tab == MasterAssetTabsEnum::PRODUCTS->value ?
                    fn () => ProductsResource::collection(IndexProductsInMasterProduct::run($masterAsset))
                    : Inertia::lazy(fn () => ProductsResource::collection(IndexProductsInMasterProduct::run($masterAsset))),

            ]
        )->table(IndexProductsInMasterProduct::make()->tableStructure(prefix: MasterAssetTabsEnum::PRODUCTS->value))
        ->table(IndexMailshots::make()->tableStructure($masterAsset))
        ->table(IndexTradeUnitsInMasterProduct::make()->tableStructure(prefix: MasterAssetTabsEnum::TRADE_UNITS->value));
    }

    public function jsonResponse(MasterAsset $masterAsset): MasterProductResource
    {
        return new MasterProductResource($masterAsset);
    }

    public function getBreadcrumbs(MasterAsset $masterAsset, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (MasterAsset $masterAsset, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Master Products')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $masterAsset->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };


        return match ($routeName) {
            'grp.masters.master_shops.show.master_products.show' =>
            array_merge(
                ShowMasterShop::make()->getBreadcrumbs($masterAsset->masterShop, $routeParameters),
                $headCrumb(
                    $masterAsset,
                    [
                        'index' => [
                            'name'       => 'grp.masters.master_shops.show.master_products.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.masters.master_shops.show.master_products.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.masters.master_departments.show.master_assets.show' =>
            array_merge(
                (new ShowMasterDepartment())->getBreadcrumbs($this->parent, $routeName, $routeParameters),
                $headCrumb(
                    $masterAsset,
                    [
                        'index' => [
                            'name'       => 'grp.masters.master_departments.show.master_assets.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.masters.master_departments.show.master_assets.show',
                            'parameters' => $routeParameters


                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show' =>
            array_merge(
                (new ShowMasterSubDepartment())->getBreadcrumbs('grp.org.shops.show.catalogue.departments.show.sub_departments.show', $routeParameters),
                $headCrumb(
                    $masterAsset,
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
            'grp.masters.master_shops.show.master_departments.show.master_products.show' =>
            array_merge(
                ShowMasterDepartment::make()->getBreadcrumbs($masterAsset->masterShop, $masterAsset->masterDepartment, $routeName, $routeParameters, $suffix),
                $headCrumb(
                    $masterAsset,
                    [
                        'index' => [
                            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_products.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_products.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.masters.master_shops.show.master_families.master_products.show' =>
            array_merge(
                ShowMasterFamily::make()->getBreadcrumbs($masterAsset->masterFamily, $routeName, $routeParameters, $suffix),
                $headCrumb(
                    $masterAsset,
                    [
                        'index' => [
                            'name'       => 'grp.masters.master_shops.show.master_families.master_products.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.masters.master_shops.show.master_families.master_products.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(MasterAsset $masterAsset, ActionRequest $request): ?array
    {
        $previous = MasterAsset::where('code', '<', $masterAsset->code)->orderBy('code', 'desc')->where('master_shop_id', $this->parent->id)->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(MasterAsset $masterAsset, ActionRequest $request): ?array
    {
        $next = MasterAsset::where('code', '>', $masterAsset->code)->orderBy('code')->where('master_shop_id', $this->parent->id)->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?MasterAsset $masterAsset, string $routeName): ?array
    {
        if (!$masterAsset) {
            return null;
        }

        return match ($routeName) {
            'grp.masters.master_assets.show' => [
                'label' => $masterAsset->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'masterProduct' => $masterAsset->slug
                    ]
                ]
            ],
            'grp.masters.master_shops.show.master_products.show' => [
                'label' => $masterAsset->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'masterShop'   => $masterAsset->masterShop->slug,
                        'masterProduct' => $masterAsset->slug
                    ]
                ]
            ],
            default => []
        };
    }
}
