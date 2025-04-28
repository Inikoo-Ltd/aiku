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
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\GrpAction;
use App\Enums\UI\SupplyChain\MasterDepartmentTabsEnum;
use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\Catalogue\FamilyResource;
use App\Http\Resources\Masters\MasterDepartmentsResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\Web\WebBlockType;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMasterDepartmentWorkshop extends GrpAction
{
    use WithDepartmentSubNavigation;


    private MasterShop $parent;

    public function handle(MasterProductCategory $masterDepartment): MasterProductCategory
    {
        return $masterDepartment;
    }

    public function asController(MasterShop $masterShop, MasterProductCategory $masterDepartment, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterShop;
        $group = group();
        $this->initialisation($group, $request)->withTab(MasterDepartmentTabsEnum::values());

        return $this->handle($masterDepartment);
    }

    public function htmlResponse(MasterProductCategory $masterDepartment, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/DepartmentMasterBlueprint',
            [
                'title'       => __('department'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($masterDepartment, $request),
                    'next'     => $this->getNext($masterDepartment, $request),
                ],
                'pageHead'    => [
                    'title'     => $masterDepartment->name,
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
                    // 'subNavigation' => $this->getDepartmentSubNavigation($masterDepartment)
                ],

                'upload_image_route' => [
                    'method' => 'post',
                    'name'       => 'grp.models.master_product_image.upload',
                    'parameters' => [
                        'masterProductCategory' => $masterDepartment->id
                    ]
                ],

                'update_route' => [
                    'method' => 'patch',
                    'name'       => 'grp.models.master_product.update',
                    'parameters' => [
                        'masterProductCategory' => $masterDepartment->id
                    ]
                ],

                'department' => MasterDepartmentsResource::make($masterDepartment),
                'web_block_types' => WebBlockTypesResource::collection(WebBlockType::where('category', WebBlockCategoryScopeEnum::DEPARTMENT->value)->get()),
                'families' => FamilyResource::collection($masterDepartment->masterFamilies()),
                'web_block_types_families' => WebBlockTypesResource::collection(WebBlockType::where('category', WebBlockCategoryScopeEnum::FAMILY->value)->get()),
            ]
        );
    }

    public function jsonResponse(MasterProductCategory $masterDepartment): DepartmentsResource
    {
        return new DepartmentsResource($masterDepartment);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (MasterProductCategory $masterDepartment, array $routeParameters, $suffix) {
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
                            'label' => $masterDepartment->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        $masterDepartment = MasterProductCategory::where('slug', $routeParameters['masterDepartment'])->first();

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
                    $masterDepartment,
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

    public function getPrevious(MasterProductCategory $masterDepartment, ActionRequest $request): ?array
    {
        $previous = MasterProductCategory::where('code', '<', $masterDepartment->code)->where('master_shop_id', $this->parent->id)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(MasterProductCategory $masterDepartment, ActionRequest $request): ?array
    {
        $next = MasterProductCategory::where('code', '>', $masterDepartment->code)->where('master_shop_id', $this->parent->id)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?MasterProductCategory $masterDepartment, string $routeName): ?array
    {
        if (!$masterDepartment) {
            return null;
        }

        return match ($routeName) {
            'grp.masters.departments.blueprint' => [
                'label' => $masterDepartment->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'masterFamily' => $masterDepartment->slug
                    ]
                ]
            ],
            'grp.masters.shops.show.departments.blueprint' => [
                'label' => $masterDepartment->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'masterShop'   => $masterDepartment->masterShop->slug,
                        'masterDepartment' => $masterDepartment->slug
                    ]
                ]
            ],
            default => []
        };
    }
}
