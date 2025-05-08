<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Enums\UI\SupplyChain\MasterDepartmentTabsEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Masters\MasterDepartmentsResource;

class ShowMasterDepartmentsWorkshop extends GrpAction
{
    use WithDepartmentSubNavigation;


    public function handle(MasterShop $masterShop): MasterShop
    {
        return $masterShop;
    }


    public function asController(MasterShop $masterShop, ActionRequest $request): MasterShop
    {
        $group = group();
        $this->initialisation($group, $request)->withTab(MasterDepartmentTabsEnum::values());

        return $this->handle($masterShop);
    }


    public function htmlResponse(MasterShop $masterShop, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/DepartmentsMasterBlueprint',
            [
                'title'       => __('department'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $masterShop,
                    $request->route()->getName(),
                ),
                'pageHead'    => [
                    'title' => $masterShop->name,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-folder-tree'],
                        'title' => __('department'),
                    ],
                ],

                'update_route' => [
                    'method'     => 'patch',
                    'name'       => 'grp.models.master_product.update',
                    'parameters' => []
                ],
                'departments' => MasterDepartmentsResource::collection($masterShop->getMasterDepartments())
            ]
        );
    }


    public function jsonResponse(MasterShop $masterShop): DepartmentsResource
    {
        return new DepartmentsResource($masterShop);
    }


    public function getBreadcrumbs(MasterShop $masterShop, string $routeName): array
    {
        return ShowMasterShop::make()->getBreadcrumbs(
            $masterShop,
            routeName: preg_replace('/blueprint$/', 'show', $routeName),
            suffix: '('.__('Blueprint').')'
        );
    }

}
