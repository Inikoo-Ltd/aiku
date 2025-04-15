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
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Enums\UI\SupplyChain\MasterDepartmentTabsEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Catalogue\Shop;
use App\Models\Goods\MasterProductCategory;
use App\Models\Goods\MasterShop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMasterDepartment extends GrpAction
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
            'Org/Catalogue/Department',
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
                                'name'       => 'shops.show.departments.remove',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false
                    ],
                    // 'subNavigation' => $this->getDepartmentSubNavigation($masterdepartment)
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => MasterDepartmentTabsEnum::navigation()
                ],

                MasterDepartmentTabsEnum::SHOWCASE->value => $this->tab == MasterDepartmentTabsEnum::SHOWCASE->value ?
                    fn () => GetMasterProductCategoryShowcase::run($masterdepartment)
                    : Inertia::lazy(fn () => GetMasterProductCategoryShowcase::run($masterdepartment)),


                MasterDepartmentTabsEnum::HISTORY->value => $this->tab == MasterDepartmentTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($masterdepartment))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($masterdepartment))),





            ]
        )

            ->table(IndexHistory::make()->tableStructure(prefix: MasterDepartmentTabsEnum::HISTORY->value));
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
