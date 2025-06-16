<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 15:31:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Inventory\Warehouse\UI;

use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseAuthorisation;
use App\Enums\UI\Inventory\WarehouseTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Inventory\WarehouseResource;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWarehouse extends OrgAction
{
    use WithActionButtons;
    use WithWarehouseAuthorisation;

    public function handle(Warehouse $warehouse): Warehouse
    {
        return $warehouse;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("inventory.{$this->warehouse->id}.edit");

        return $request->user()->authTo("inventory.{$this->warehouse->id}.view");
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Warehouse
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(WarehouseTabsEnum::values());

        return $this->handle($warehouse);
    }


    public function htmlResponse(Warehouse $warehouse, ActionRequest $request): Response
    {
        $routeParameters = $request->route()->originalParameters();

        return Inertia::render(
            'Org/Warehouse/Warehouse',
            [
                'title'       => __('warehouse'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'navigation'  => [
                    'previous' => $this->getPrevious($warehouse, $request),
                    'next'     => $this->getNext($warehouse, $request),
                ],
                'pageHead'    => [
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'warehouse'],
                            'title' => __('warehouse')
                        ],
                    'title'   => $warehouse->name,
                    'model'   => __('Warehouse'),
                    'actions' => [
                        $this->canEdit ?
                            [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('new location'),
                                'label'   => __('new location'),
                                'route'   => [
                                    'name'       => 'grp.org.warehouses.show.infrastructure.locations.create',
                                    'parameters' => $request->route()->originalParameters()
                                ]
                            ]
                            : null,
                        $this->canEdit ? $this->getEditActionIcon($request) : null,

                    ],
                ],

                'tabs'     => [

                    'current'    => $this->tab,
                    'navigation' => WarehouseTabsEnum::navigation(),
                ],

                WarehouseTabsEnum::SHOWCASE->value => $this->tab == WarehouseTabsEnum::SHOWCASE->value ?
                    fn () => GetWarehouseShowcase::run($warehouse, $routeParameters)
                    : Inertia::lazy(fn () => GetWarehouseShowcase::run($warehouse, $routeParameters)),


                WarehouseTabsEnum::HISTORY->value => $this->tab == WarehouseTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($warehouse))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($warehouse)))

            ]
        )->table(IndexHistory::make()->tableStructure(prefix: WarehouseTabsEnum::HISTORY->value));
    }


    public function jsonResponse(Warehouse $warehouse): WarehouseResource
    {
        return new WarehouseResource($warehouse);
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

    public function getPrevious(Warehouse $warehouse, ActionRequest $request): ?array
    {
        $previous = Warehouse::where('code', '<', $warehouse->code)->where('organisation_id', $warehouse->organisation_id)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Warehouse $warehouse, ActionRequest $request): ?array
    {
        $next = Warehouse::where('code', '>', $warehouse->code)->where('organisation_id', $warehouse->organisation_id)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Warehouse $warehouse, string $routeName): ?array
    {
        if (!$warehouse) {
            return null;
        }

        return match ($routeName) {
            'grp.org.warehouses.show.infrastructure.dashboard' => [
                'label' => $warehouse->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'warehouse'    => $warehouse->slug
                    ]

                ]
            ]
        };
    }
}
