<?php

/*
 * author Arya Permana - Kirin
 * created on 14-05-2025-11h-09m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Shipper\UI;

use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Enums\UI\Dispatch\ShipperTabsEnum;
use App\Models\Dispatching\Shipper;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowShipper extends OrgAction
{
    use WithActionButtons;
    public function handle(Shipper $shipper): Shipper
    {
        return $shipper;
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, Shipper $shipper, ActionRequest $request): Shipper
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(ShipperTabsEnum::values());

        return $this->handle($shipper);
    }

    public function htmlResponse(Shipper $shipper, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Dispatching/Shipper',
            [
                'title'       => __('shipper'),
                'breadcrumbs' => $this->getBreadcrumbs($shipper, $request->route()->getName(), $request->route()->originalParameters()),
                'pageHead' => [
                    'title'     => $shipper->name,
                    'model'     => __('shipper'),
                    'icon'      => [
                        'icon'  => ['fal', 'fa-shipping-fast'],
                        'title' => __('shipper')
                    ],
                    'actions'    => [
                        [
                            'type'    => 'button',
                            'tooltip' => __('Edit'),
                            'icon'    => 'fal fa-pencil',
                            'label'   => 'edit',
                            'style'   => 'edit',
                            'route'   => [
                                'name'       => preg_replace('/(show|dashboard)$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()

                            ]
                        ],

                    ]
                ],
                'tabs'          => [
                    'current'    => $this->tab,
                    'navigation' => ShipperTabsEnum::navigation()

                ],

                ShipperTabsEnum::SHOWCASE->value => $this->tab == ShipperTabsEnum::SHOWCASE->value ?
                    fn () => GetShipperShowcase::run($shipper)
                    : Inertia::lazy(fn () => GetShipperShowcase::run($shipper)),
            ]
        );
    }

    public function getBreadcrumbs(Shipper $shipper, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Shipper $shipper, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Shippers')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $shipper->name,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.dispatching.shippers.edit',
            'grp.org.warehouses.show.dispatching.shippers.show',
            => array_merge(
                ShowWarehouse::make()->getBreadcrumbs(
                    Arr::only($routeParameters, ['organisation', 'warehouse'])
                ),
                $headCrumb(
                    $shipper,
                    [
                        'index' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.shippers.current.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.shippers.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse', 'shipper'])
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }
}
