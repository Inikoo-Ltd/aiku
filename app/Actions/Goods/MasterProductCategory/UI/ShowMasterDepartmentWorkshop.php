<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Goods\MasterProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\GrpAction;
use App\Enums\UI\SupplyChain\MasterDepartmentTabsEnum;
use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\Catalogue\FamilyResource;
use App\Http\Resources\Goods\Catalogue\MasterDepartmentsResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Catalogue\Shop;
use App\Models\Goods\MasterProductCategory;
use App\Models\Goods\MasterShop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\WebBlockType;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMasterDepartmentWorkshop extends GrpAction
{
    use WithDepartmentSubNavigation;


    private MasterShop $parent;

    public function handle(MasterProductCategory $masterdepartment): MasterProductCategory
    {
        return $masterdepartment;
    }

    public function asController(MasterShop $masterShop, MasterProductCategory $masterDepartment, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterShop;
        $group = group();
        $this->initialisation($group, $request)->withTab(MasterDepartmentTabsEnum::values());

        return $this->handle($masterDepartment);
    }
    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->authTo("goods.{$this->group->id}.edit");

        return $request->user()->authTo("goods.{$this->group->id}.view");
    }

    public function htmlResponse(MasterProductCategory $masterdepartment, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/DepartementMasterBlueprint',
            [
                'title'       => __('department'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($masterdepartment, $request),
                    'next'     => $this->getNext($masterdepartment, $request),
                ],
                'pageHead'    => [
                    'title'     => $masterdepartment->name,
                    'icon'      => [
                        'icon'  => ['fal', 'fa-folder-tree'],
                        'title' => __('department')
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'primary',
                            'label' => 'save',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ]
                    ],
                    // 'subNavigation' => $this->getDepartmentSubNavigation($masterdepartment)
                ],

                'upload_image_route' => [
                    'method' => 'post',
                    'name'       => 'grp.models.master_product_image.upload',
                    'parameters' => [
                        'masterProductCategory' => $masterdepartment->id
                    ]
                ],

                'update_route' => [
                    'method' => 'patch',
                    'name'       => 'grp.models.master_product.update',
                    'parameters' => [
                        'masterProductCategory' => $masterdepartment->id
                    ]
                ],

                'department' => MasterDepartmentsResource::make($masterdepartment),
                'web_block_types' => WebBlockTypesResource::collection(WebBlockType::where('category', WebBlockCategoryScopeEnum::DEPARTMENT->value)->get()),
                'families' => FamilyResource::collection($masterdepartment->families()),
                'web_block_types_families' => WebBlockTypesResource::collection(WebBlockType::where('category', WebBlockCategoryScopeEnum::FAMILY->value)->get()),
            ]
        );
    }

    public function jsonResponse(MasterProductCategory $masterdepartment): DepartmentsResource
    {
        return new DepartmentsResource($masterdepartment);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (MasterProductCategory $masterdepartment, array $routeParameters, $suffix) {
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
                            'label' => $masterdepartment->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        $masterdepartment = MasterProductCategory::where('slug', $routeParameters['masterDepartment'])->first();

        return match ($routeName) {
            /*
            'shops.departments.show' =>
            array_merge(
                IndexShops::make()->getBreadcrumbs(),
                $headCrumb(
                    $routeParameters['department'],
                    [
                        'index' => [
                            'name'       => 'shops.departments.index',
                            'parameters' => []
                        ],
                        'model' => [
                            'name'       => 'shops.departments.show',
                            'parameters' => [
                                $routeParameters['department']->slug
                            ]
                        ]
                    ],
                    $suffix
                )
            ),
            */
            'grp.org.shops.show.catalogue.departments.show' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $masterdepartment,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(MasterProductCategory $masterdepartment, ActionRequest $request): ?array
    {
        $previous = MasterProductCategory::where('code', '<', $masterdepartment->code)->where('master_shop_id', $this->parent->id)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(MasterProductCategory $masterdepartment, ActionRequest $request): ?array
    {
        $next = MasterProductCategory::where('code', '>', $masterdepartment->code)->where('master_shop_id', $this->parent->id)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?MasterProductCategory $masterdepartment, string $routeName): ?array
    {
        if (!$masterdepartment) {
            return null;
        }

        return match ($routeName) {
            /*
            'shops.departments.show' => [
                'label' => $masterdepartment->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'department' => $masterdepartment->slug
                    ]
                ]
            ],
            */
            // 'grp.org.shops.show.catalogue.departments.show' => [
            //     'label' => $masterdepartment->name,
            //     'route' => [
            //         'name'       => $routeName,
            //         'parameters' => [
            //             'organisation' => $masterdepartment->organisation->slug,
            //             'shop'         => $masterdepartment->shop->slug,
            //             'department'   => $masterdepartment->slug
            //         ]
            //     ]
            // ],

            default => []
        };
    }
}
