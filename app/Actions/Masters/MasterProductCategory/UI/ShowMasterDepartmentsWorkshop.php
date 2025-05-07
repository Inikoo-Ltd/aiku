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
use App\Enums\UI\SupplyChain\MasterDepartmentTabsEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMasterDepartmentsWorkshop extends GrpAction
{
    use WithDepartmentSubNavigation;

    private MasterShop|Group $parent;

    /**
     * Handles the main action logic.
     */
    public function handle(MasterShop $masterShop): MasterShop
    {
        return $masterShop;
    }

    /**
     * Action execution when scoped within a group context.
     */
    public function inGroup(MasterShop $masterShop, ActionRequest $request): MasterShop
    {
        $group        = group();
        $this->parent = $group;
        $this->initialisation($group, $request)->withTab(MasterDepartmentTabsEnum::values());

        return $this->handle($masterShop);
    }

    /**
     * Controller entry point for the action.
     */
    public function asController(MasterShop $masterShop, ActionRequest $request): MasterShop
    {
        $this->parent = $masterShop;
        $group        = group();
        $this->initialisation($group, $request)->withTab(MasterDepartmentTabsEnum::values());

        return $this->handle($masterShop);
    }

    /**
     * HTML response for Inertia-based frontend rendering.
     */
    public function htmlResponse(MasterShop $masterShop, ActionRequest $request): Response
    {
        return Inertia::render('Goods/DepartmentMasterBlueprint', [
            'title'       => __('department'),
            'breadcrumbs' => $this->getBreadcrumbs(
                $masterShop,
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'navigation'  => [
                'previous' => $this->getPrevious($masterShop, $request),
                'next'     => $this->getNext($masterShop, $request),
            ],
            'pageHead'    => [
                'title' => $masterShop->name,
                'icon'  => [
                    'icon'  => ['fal', 'fa-folder-tree'],
                    'title' => __('department'),
                ],
            ],
            'department'  => 'halooo'
        ]);
    }

    /**
     * JSON response (typically for API use).
     */
    public function jsonResponse(MasterShop $masterShop): DepartmentsResource
    {
        return new DepartmentsResource($masterShop);
    }

    /**
     * Generates breadcrumb trail for the department blueprint page.
     */
    public function getBreadcrumbs(MasterShop|Group $parent, string $routeName, array $routeParameters): array
    {
        return [];
        return ShowMasterDepartment::make()->getBreadcrumbs(
            $parent,
            $department,
            routeName: preg_replace('/blueprint$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Blueprint').')'
        );
    }

    /**
     * Gets the previous department (based on code).
     */
    public function getPrevious(MasterShop $masterShop, ActionRequest $request): ?array
    {
        return [];
        $previous = MasterProductCategory::where('code', '<', $masterShop->code)
            ->where('master_shop_id', $this->parent->id)
            ->orderBy('code', 'desc')
            ->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    /**
     * Gets the next department (based on code).
     */
    public function getNext(MasterShop $masterShop, ActionRequest $request): ?array
    {
        return [];
        $next = MasterProductCategory::where('code', '>', $masterShop->code)
            ->where('master_shop_id', $this->parent->id)
            ->orderBy('code')
            ->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    /**
     * Builds the navigation structure for previous/next buttons.
     */
    private function getNavigation(?MasterShop $masterShop, string $routeName): ?array
    {
        if (!$masterShop) {
            return null;
        }

        return match ($routeName) {
            'grp.masters.department.blueprint' => [
                'label' => $masterShop->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'masterDepartment' => $masterShop->slug,
                    ],
                ],
            ],
            'grp.masters.shops.show.department.blueprint' => [
                'label' => $masterShop->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'masterShop'       => $masterShop->slug,
                        'masterDepartment' => $masterShop->slug,
                    ],
                ],
            ],
            default => [],
        };
    }
}
