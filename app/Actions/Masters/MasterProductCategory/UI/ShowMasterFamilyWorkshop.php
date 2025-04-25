<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\GrpAction;
use App\Enums\UI\SupplyChain\MasterFamilyTabsEnum;
use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\Catalogue\FamilyResource;
use App\Http\Resources\Goods\Catalogue\MasterProductsResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\Web\WebBlockType;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMasterFamilyWorkshop extends GrpAction
{
    use WithFamilySubNavigation;

    private MasterShop $parent;

    public function handle(MasterProductCategory $masterfamily): MasterProductCategory
    {
        return $masterfamily;
    }


    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->authTo("goods.{$this->group->id}.edit");

        return $request->user()->authTo("goods.{$this->group->id}.view");
    }

    public function asController(MasterShop $masterShop, MasterProductCategory $masterFamily, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterShop;
        $group = group();

        $this->initialisation($group, $request)->withTab(MasterFamilyTabsEnum::values());

        return $this->handle($masterFamily);
    }


    public function htmlResponse(MasterProductCategory $masterfamily, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/FamilyMasterBlueprint',
            [
                'title'       => __('family'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $masterfamily,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($masterfamily, $request),
                    'next'     => $this->getNext($masterfamily, $request),
                ],
                'pageHead'    => [
                    'title'   => $masterfamily->name,
                    'model'   => '',
                    'icon'    => [
                        'icon'  => ['fal', 'fa-folder'],
                        'title' => __('department')
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
                                'name'       => 'shops.show.families.remove',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false
                    ],
                    // 'subNavigation' => $this->getFamilySubNavigation($masterfamily, $this->parent, $request)

                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => MasterFamilyTabsEnum::navigation()
                ],

                'upload_image_route' => [
                    'method' => 'post',
                    'name'       => 'grp.models.master_product_image.upload',
                    'parameters' => [
                        'masterProductCategory' => $masterfamily->id
                    ]
                ],

                'update_route' => [
                    'method' => 'patch',
                    'name'       => 'grp.models.master_product.update',
                    'parameters' => [
                        'masterProductCategory' => $masterfamily->id
                    ]
                ],

                'family' => FamilyResource::make($masterfamily),
                'web_block_types' => WebBlockTypesResource::collection(WebBlockType::where('category', WebBlockCategoryScopeEnum::FAMILY->value)->get()),
                'assets' => MasterProductsResource::collection($masterfamily->masterAssets()->get()),
                'web_block_types_products' => WebBlockTypesResource::collection(WebBlockType::where('category', WebBlockCategoryScopeEnum::PRODUCT->value)->get()),
            ]
        );
    }


    public function jsonResponse(MasterProductCategory $masterfamily): DepartmentsResource
    {
        return new DepartmentsResource($masterfamily);
    }

    public function getBreadcrumbs(MasterProductCategory $masterfamily, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (MasterProductCategory $masterfamily, array $routeParameters, $suffix) {
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
                            'label' => $masterfamily->code,
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
                    $masterfamily,
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
                (new ShowMasterDepartment())->getBreadcrumbs('grp.org.shops.show.catalogue.departments.show', $routeParameters),
                $headCrumb(
                    $masterfamily,
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
            'grp.org.shops.show.catalogue.departments.show.sub-departments.show.family.show' =>
            array_merge(
                (new ShowMasterSubDepartment())->getBreadcrumbs('grp.org.shops.show.catalogue.departments.show.sub-departments.show', $routeParameters),
                $headCrumb(
                    $masterfamily,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.sub-departments.show.family.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.sub-departments.show.family.show',
                            'parameters' => $routeParameters


                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(MasterProductCategory $masterfamily, ActionRequest $request): ?array
    {
        $previous = MasterProductCategory::where('code', '<', $masterfamily->code)->orderBy('code', 'desc')->where('master_shop_id', $this->parent->id)->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(MasterProductCategory $masterfamily, ActionRequest $request): ?array
    {
        $next = MasterProductCategory::where('code', '>', $masterfamily->code)->orderBy('code')->where('master_shop_id', $this->parent->id)->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?MasterProductCategory $masterfamily, string $routeName): ?array
    {
        if (!$masterfamily) {
            return null;
        }

        return match ($routeName) {
            'shops.families.show' => [
                'label' => $masterfamily->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'department' => $masterfamily->slug
                    ]
                ]
            ],
            'shops.show.families.show' => [
                'label' => $masterfamily->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'shop'       => $masterfamily->shop->slug,
                        'department' => $masterfamily->slug
                    ]
                ]
            ],
            default => [] // Add a default case to handle unmatched route names
        };
    }
}
