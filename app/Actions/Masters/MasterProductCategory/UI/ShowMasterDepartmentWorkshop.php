<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\GrpAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\SupplyChain\MasterDepartmentTabsEnum;
use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\Catalogue\FamilyResource;
use App\Http\Resources\Masters\MasterDepartmentsResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use App\Models\Web\WebBlockType;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMasterDepartmentWorkshop extends GrpAction
{
    use WithDepartmentSubNavigation;


    private MasterShop|Group $parent;

    public function handle(MasterProductCategory $masterDepartment): MasterProductCategory
    {
        return $masterDepartment;
    }

    public function inGroup(MasterProductCategory $masterDepartment, ActionRequest $request): MasterProductCategory
    {
        $group        = group();
        $this->parent = $group;
        $this->initialisation($group, $request)->withTab(MasterDepartmentTabsEnum::values());

        return $this->handle($masterDepartment);
    }

    public function asController(MasterShop $masterShop, MasterProductCategory $masterDepartment, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterShop;
        $group        = group();
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
                    $this->parent,
                    $masterDepartment,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($masterDepartment, $request),
                    'next'     => $this->getNext($masterDepartment, $request),
                ],
                'pageHead'    => [
                    'title' => $masterDepartment->name,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-folder-tree'],
                        'title' => __('department')
                    ],
                ],

                'upload_image_route' => [
                    'method'     => 'post',
                    'name'       => 'grp.models.master_product_image.upload',
                    'parameters' => [
                        'masterProductCategory' => $masterDepartment->id
                    ]
                ],

                'update_route' => [
                    'method'     => 'patch',
                    'name'       => 'grp.models.master_product.update',
                    'parameters' => [
                        'masterProductCategory' => $masterDepartment->id
                    ]
                ],

                'department'               => MasterDepartmentsResource::make($masterDepartment),
                'web_block_types'          => WebBlockTypesResource::collection(WebBlockType::where('category', WebBlockCategoryScopeEnum::DEPARTMENT->value)->get()),
                'families'                 => FamilyResource::collection(
                    $masterDepartment->children()->where('type', ProductCategoryTypeEnum::FAMILY)->where('status', true)->get()
                ),
                'web_block_types_families' => WebBlockTypesResource::collection(WebBlockType::where('category', WebBlockCategoryScopeEnum::FAMILY->value)->get()),
            ]
        );
    }

    public function jsonResponse(MasterProductCategory $masterDepartment): DepartmentsResource
    {
        return new DepartmentsResource($masterDepartment);
    }

    public function getBreadcrumbs(MasterShop|Group $parent, MasterProductCategory $department, string $routeName, array $routeParameters): array
    {
        return ShowMasterDepartment::make()->getBreadcrumbs(
            $parent,
            $department,
            routeName: preg_replace('/blueprint$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Blueprint').')'
        );
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
            'grp.masters.department.blueprint' => [
                'label' => $masterDepartment->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'masterDepartment' => $masterDepartment->slug
                    ]
                ]
            ],
            'grp.masters.shops.show.department.blueprint' => [
                'label' => $masterDepartment->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'masterShop'       => $masterDepartment->masterShop->slug,
                        'masterDepartment' => $masterDepartment->slug
                    ]
                ]
            ],
            default => []
        };
    }
}
