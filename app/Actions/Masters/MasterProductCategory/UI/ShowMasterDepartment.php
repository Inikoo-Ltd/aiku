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
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\Masters\UI\ShowMastersDashboard;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\UI\SupplyChain\MasterDepartmentTabsEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMasterDepartment extends GrpAction
{
    use WithDepartmentSubNavigation;
    use WithMastersAuthorisation;


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
            'Org/Catalogue/Department',
            [
                'title'       => __('department'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->parent,
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
                            'style' => 'edit',
                            'label' => 'blueprint',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'blueprint', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ],
                        $this->canDelete ? [
                            'type'  => 'button',
                            'style' => 'delete',
                            'route' => [
                                'name'       => 'shops.show.departments.remove',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => MasterDepartmentTabsEnum::navigation()
                ],

                MasterDepartmentTabsEnum::SHOWCASE->value => $this->tab == MasterDepartmentTabsEnum::SHOWCASE->value ?
                    fn () => GetMasterProductCategoryShowcase::run($masterDepartment)
                    : Inertia::lazy(fn () => GetMasterProductCategoryShowcase::run($masterDepartment)),


                MasterDepartmentTabsEnum::HISTORY->value => $this->tab == MasterDepartmentTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($masterDepartment))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($masterDepartment))),





            ]
        )

            ->table(IndexHistory::make()->tableStructure(prefix: MasterDepartmentTabsEnum::HISTORY->value));
    }


    public function jsonResponse(MasterProductCategory $masterDepartment): DepartmentsResource
    {
        return new DepartmentsResource($masterDepartment);
    }

    public function getBreadcrumbs(Group|MasterShop|MasterProductCategory $parent, string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Master departments'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.masters.departments.index' =>
            array_merge(
                ShowMastersDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.masters.shops.show.departments.index' =>
            array_merge(
                ShowMasterShop::make()->getBreadcrumbs($parent, $routeName),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
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
            'grp.masters.departments.show' => [
                'label' => $masterDepartment->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'masterFamily' => $masterDepartment->slug
                    ]
                ]
            ],
            'grp.masters.shops.show.departments.show' => [
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
