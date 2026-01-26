<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 15:31:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Inventory\PickingTrolley\UI;

use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseAuthorisation;
use App\Enums\UI\Inventory\PickingTrolleysTabsEnum;
use App\Enums\UI\Inventory\WarehouseTabsEnum;
use App\Http\Resources\Inventory\PickingTrolleyResource;
use App\Models\Inventory\PickingTrolley;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPickingTrolley extends OrgAction
{
    use WithActionButtons;
    use WithWarehouseAuthorisation;

    public function handle(PickingTrolley $pickingTrolley): PickingTrolley
    {
        return $pickingTrolley;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("inventory.{$this->warehouse->id}.edit");

        return $request->user()->authTo("inventory.{$this->warehouse->id}.view");
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, PickingTrolley $pickingTrolley, ActionRequest $request): PickingTrolley
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(WarehouseTabsEnum::values());

        return $this->handle($pickingTrolley);
    }

    public function htmlResponse(PickingTrolley $pickingTrolley, ActionRequest $request): Response
    {
        $routeParameters = $request->route()->originalParameters();

        return Inertia::render(
            'Org/Warehouse/PickingTrolley',
            [
                'title'       => __('Warehouse'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'navigation'  => [
                    'previous' => $this->getPrevious($pickingTrolley, $request),
                    'next'     => $this->getNext($pickingTrolley, $request),
                ],
                'pageHead'    => [
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'picking trolley'],
                            'title' => __('picking trolley')
                        ],
                    'title'   => $pickingTrolley->name,
                    'model'   => __('Picking trolley'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Edit'),
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.inventory.picking_trolleys.edit',
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],

                'tabs'     => [
                    'current'    => $this->tab,
                    'navigation' => PickingTrolleysTabsEnum::navigation(),
                ],

                /*WarehouseTabsEnum::SHOWCASE->value => $this->tab == WarehouseTabsEnum::SHOWCASE->value ?
                    fn () => GetWarehouseShowcase::run($warehouse, $routeParameters)
                    : Inertia::lazy(fn () => GetWarehouseShowcase::run($warehouse, $routeParameters)),*/

            ]
        );
    }


    public function jsonResponse(Warehouse $warehouse): PickingTrolleyResource
    {
        return new PickingTrolleyResource($warehouse);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $warehouse = Warehouse::where('slug', $routeParameters['warehouse'])->first();

        return array_merge(
            (new ShowOrganisationDashboard())->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.warehouses.index',
                                'parameters' => $routeParameters['organisation']
                            ],
                            'label' => __('Warehouses'),
                            'icon'  => 'fal fa-bars'
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.infrastructure.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => $warehouse?->code,
                            'icon'  => 'fal fa-bars'
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ]
        );
    }

    public function getPrevious(PickingTrolley $pickingTrolley, ActionRequest $request): ?array
    {
        $previous = PickingTrolley::where('code', '<', $pickingTrolley->code)->where('organisation_id', $pickingTrolley->organisation_id)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(PickingTrolley $pickingTrolley, ActionRequest $request): ?array
    {
        $next = PickingTrolley::where('code', '>', $pickingTrolley->code)->where('organisation_id', $pickingTrolley->organisation_id)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?PickingTrolley $pickingTrolley, string $routeName): ?array
    {
        if (!$pickingTrolley) {
            return null;
        }

        return match ($routeName) {
            'grp.org.warehouses.show.inventory.picking_trolleys.show' => [
                'label' => $pickingTrolley->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'warehouse' => $pickingTrolley->warehouse->slug,
                        'pickingTrolley'    => $pickingTrolley->slug
                    ]
                ]
            ]
        };
    }
}
