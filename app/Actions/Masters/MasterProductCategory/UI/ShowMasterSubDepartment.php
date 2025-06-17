<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\GrpAction;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Masters\MasterProductCategory\WithMasterSubDepartmentSubNavigation;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\UI\SupplyChain\MasterSubDepartmentTabsEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMasterSubDepartment extends GrpAction
{
    use WithMasterSubDepartmentSubNavigation;
    use WithMastersAuthorisation;


    private MasterShop|MasterProductCategory $parent;

    public function handle(MasterProductCategory $masterSubDepartment): MasterProductCategory
    {
        return $masterSubDepartment;
    }


    public function asController(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterShop;
        $group        = group();
        $this->initialisation($group, $request)->withTab(MasterSubDepartmentTabsEnum::values());

        return $this->handle($masterSubDepartment);
    }

    public function inMasterDepartment(MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterDepartment;
        $group        = group();
        $this->initialisation($group, $request)->withTab(MasterSubDepartmentTabsEnum::values());

        return $this->handle($masterSubDepartment);
    }

    public function htmlResponse(MasterProductCategory $masterSubDepartment, ActionRequest $request): Response
    {
        $subNavigation = false;

        if ($this->parent instanceof MasterProductCategory) {
            $subNavigation = $this->getMasterSubDepartmentSubNavigation($masterSubDepartment);
        }

        return Inertia::render(
            'Masters/MasterSubDepartment',
            [
                'title'       => __('Sub-department'),
                 'breadcrumbs' => $this->getBreadcrumbs(
                     $masterSubDepartment,
                     $request->route()->getName(),
                     $request->route()->originalParameters()
                 ),
                'navigation'  => [
                    'previous' => $this->getPrevious($masterSubDepartment, $request),
                    'next'     => $this->getNext($masterSubDepartment, $request),
                ],
                'pageHead'    => [
                    'title'   => $masterSubDepartment->name,
                    'model'   => __('Sub-department'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-folder-tree'],
                        'title' => __('Sub-department')
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
                    'subNavigation' => $subNavigation,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => MasterSubDepartmentTabsEnum::navigation()
                ],

                MasterSubDepartmentTabsEnum::SHOWCASE->value => $this->tab == MasterSubDepartmentTabsEnum::SHOWCASE->value ?
                    fn () => GetMasterProductCategoryShowcase::run($masterSubDepartment)
                    : Inertia::lazy(fn () => GetMasterProductCategoryShowcase::run($masterSubDepartment)),

                MasterSubDepartmentTabsEnum::HISTORY->value => $this->tab == MasterSubDepartmentTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($masterSubDepartment))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($masterSubDepartment))),


            ]
        )
            ->table(IndexHistory::make()->tableStructure(prefix: MasterSubDepartmentTabsEnum::HISTORY->value));
    }


    public function jsonResponse(MasterProductCategory $masterSubDepartment): DepartmentsResource
    {
        return new DepartmentsResource($masterSubDepartment);
    }

    public function getBreadcrumbs(MasterProductCategory $masterSubDepartment, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (MasterProductCategory $masterSubDepartment, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Sub-departments')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $masterSubDepartment->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };



        return match ($routeName) {

            'grp.org.shops.show.catalogue.departments.show.sub_departments.show' =>
            array_merge(
                (new ShowMasterDepartment())->getBreadcrumbs($masterSubDepartment->masterShop, $masterSubDepartment->masterDepartment, 'grp.org.shops.show.catalogue.departments.show', $routeParameters),
                $headCrumb(
                    $masterSubDepartment,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show',
                            'parameters' => $routeParameters


                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(MasterProductCategory $masterSubDepartment, ActionRequest $request): ?array
    {
        $previous = MasterProductCategory::where('code', '<', $masterSubDepartment->code)->orderBy('code', 'desc')->where('master_shop_id', $this->parent->id)->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(MasterProductCategory $masterSubDepartment, ActionRequest $request): ?array
    {
        $next = MasterProductCategory::where('code', '>', $masterSubDepartment->code)->orderBy('code')->where('master_shop_id', $this->parent->id)->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?MasterProductCategory $masterSubDepartment, string $routeName): ?array
    {
        if (!$masterSubDepartment) {
            return null;
        }

        return match ($routeName) {
            'grp.masters.shops.show.sub-departments.show' => [
                'label' => $masterSubDepartment->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'masterShop'          => $masterSubDepartment->masterShop->slug,
                        'masterSubDepartment' => $masterSubDepartment->slug
                    ]
                ]
            ],
            default => [] // Add a default case to handle unmatched route names
        };
    }
}
