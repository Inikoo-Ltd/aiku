<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterCollection\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\Comms\Mailshot\UI\IndexMailshots;
use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\UI\SupplyChain\MasterCollectionTabsEnum;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\Http\Resources\Masters\MasterCollectionResource;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMasterCollection extends GrpAction
{
    use WithCollectionSubNavigation;
    use WithMastersAuthorisation;

    private MasterShop|Group $parent;

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
                'title'       => __('collection'),
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
                        'title' => __('collection')
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
                                'name'       => 'shops.show.collections.remove',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => MasterCollectionTabsEnum::navigation()
                ],

                MasterCollectionTabsEnum::SHOWCASE->value => $this->tab == MasterCollectionTabsEnum::SHOWCASE->value ?
                    fn () => MasterCollectionResource::make($masterCollection)
                    : Inertia::lazy(fn () => MasterCollectionResource::make($masterCollection)),
            ]
        )
            ->table(IndexMailshots::make()->tableStructure($masterCollection));
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
                            'label' => __('Collections')
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
            'grp.org.shops.show.catalogue.collections.show' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $masterCollection,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.catalogue.collections.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.catalogue.collections.show',
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
            'grp.masters.master_collections.show' => [
                'label' => $masterCollection->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'masterCollection' => $masterCollection->slug
                    ]
                ]
            ],
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
