<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterCollection\UI;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterDepartment;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\UI\SupplyChain\MasterCollectionTabsEnum;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMasterCollection extends GrpAction
{
    use WithMastersAuthorisation;

    private MasterShop|MasterProductCategory|Group $parent;

    public function handle(MasterCollection $masterCollection): MasterCollection
    {
        return $masterCollection;
    }

    public function asController(MasterShop $masterShop, MasterCollection $masterCollection, ActionRequest $request): MasterCollection
    {
        $this->parent = $masterShop;
        $group = group();

        $this->initialisation($group, $request)->withTab(MasterCollectionTabsEnum::values());

        return $this->handle($masterCollection);
    }

    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterCollection $masterCollection, ActionRequest $request): MasterCollection
    {
        $this->parent = $masterDepartment;
        $group = group();

        $this->initialisation($group, $request)->withTab(MasterCollectionTabsEnum::values());

        return $this->handle($masterCollection);
    }

    public function inMasterSubDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterCollection $masterCollection, ActionRequest $request): MasterCollection
    {
        $this->parent = $masterSubDepartment;
        $group = group();

        $this->initialisation($group, $request)->withTab(MasterCollectionTabsEnum::values());

        return $this->handle($masterCollection);
    }

    public function inMasterSubDepartmentInMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, MasterCollection $masterCollection, ActionRequest $request): MasterCollection
    {
        $this->parent = $masterSubDepartment;
        $group = group();

        $this->initialisation($group, $request)->withTab(MasterCollectionTabsEnum::values());

        return $this->handle($masterCollection);
    }

    public function inGroup(MasterCollection $masterCollection, ActionRequest $request): MasterCollection
    {
        $group        = group();
        $this->parent = $group;
        $this->initialisation($group, $request)->withTab(MasterCollectionTabsEnum::values());

        return $this->handle($masterCollection);
    }

    public function htmlResponse(MasterCollection $masterCollection, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Catalogue/Collection',
            [
                'title'       => __('master collection'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $masterCollection,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($masterCollection, $request),
                    'next'     => $this->getNext($masterCollection, $request),
                ],
                'pageHead'    => [
                    'title'   => $masterCollection->name,
                    'model'   => '',
                    'icon'    => [
                        'icon'  => ['fal', 'fa-layer-group'],
                        'title' => __('master collection')
                    ],
                    'actions' => [],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => MasterCollectionTabsEnum::navigation()
                ],

                MasterCollectionTabsEnum::SHOWCASE->value => $this->tab == MasterCollectionTabsEnum::SHOWCASE->value ?
                    fn () => GetMasterCollectionShowcase::run($masterCollection)
                    : Inertia::lazy(fn () => GetMasterCollectionShowcase::run($masterCollection)),
            ]
        );
    }

    public function jsonResponse(MasterCollection $masterCollection): CollectionsResource
    {
        return new CollectionsResource($masterCollection);
    }

    public function getBreadcrumbs(MasterCollection $masterCollection, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (MasterCollection $masterCollection, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Master Collections')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $masterCollection->code,
                        ],
                    ],
                    'suffix'         => $suffix,
                ],
            ];
        };

        return match ($routeName) {
            'grp.masters.master_shops.show.master_collections.show' =>
            array_merge(
                ShowMasterShop::make()->getBreadcrumbs($masterCollection->masterShop, $routeParameters),
                $headCrumb(
                    $masterCollection,
                    [
                        'index' => [
                            'name'       => 'grp.masters.master_shops.show.master_collections.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.masters.master_shops.show.master_collections.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.masters.master_shops.show.master_departments.show.master_collections.show' =>
            array_merge(
                ShowMasterDepartment::make()->getBreadcrumbs($masterCollection->masterShop, $this->parent, $routeName, $routeParameters, $suffix),
                $headCrumb(
                    $masterCollection,
                    [
                        'index' => [
                            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_collections.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.masters.master_shops.show.master_departments.show.master_collections.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(MasterCollection $masterCollection, ActionRequest $request): ?array
    {
        $previous = MasterCollection::where('code', '<', $masterCollection->code)->orderBy('code', 'desc')->where('master_shop_id', $this->parent->id)->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(MasterCollection $masterCollection, ActionRequest $request): ?array
    {
        $next = MasterCollection::where('code', '>', $masterCollection->code)->orderBy('code')->where('master_shop_id', $this->parent->id)->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?MasterCollection $masterCollection, string $routeName): ?array
    {
        if (!$masterCollection) {
            return null;
        }

        return match ($routeName) {
            'grp.masters.master_shops.show.master_collections.show' => [
                'label' => $masterCollection->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'masterShop'       => $masterCollection->masterShop->slug,
                        'masterCollection' => $masterCollection->slug
                    ]
                ]
            ],
            default => []
        };
    }
}
