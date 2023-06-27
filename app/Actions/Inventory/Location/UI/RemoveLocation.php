<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 10:58:06 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location\UI;

use App\Actions\InertiaAction;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class RemoveLocation extends InertiaAction
{
    public function handle(Location $location): Location
    {
        return $location;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasPermissionTo("inventory.edit");
    }

    public function asController(Location $location, ActionRequest $request): Location
    {
        $this->initialisation($request);

        return $this->handle($location);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehouse(Warehouse $warehouse, Location $location, ActionRequest $request): Location
    {

        $this->initialisation($request);

        return $this->handle($location);
    }

    /** @noinspection PhpUnusedParameterInspection */
    function inWarehouseInWarehouseArea(Warehouse $warehouse, WarehouseArea $warehouseArea, Location $location, ActionRequest $request): Location
    {
        $this->initialisation($request);

        return $this->handle($location);
    }


    public function getAction($route): array
    {
        return  [
            'buttonLabel' => __('Delete'),
            'title'       => __('Delete location'),
            'text'        => __("This action will delete this location"),
            'route'       => $route
        ];
    }

    public function htmlResponse(Location $location, ActionRequest $request): Response
    {
        return Inertia::render(
            'RemoveModel',
            [
                'title'       => __('delete location'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->parameters
                ),
                'pageHead'    => [
                    'icon'  =>
                        [
                            'icon'  => ['fal', 'fa-inventory'],
                            'title' => __('location')
                        ],
                    'title' => $location->slug,
                    'actions'=>[
                        [
                            'type'=>'button',
                            'style'=>'cancel',
                            'route' => [
                                'name'       => preg_replace('/remove$/', 'show', $this->routeName),
                                'parameters' => array_values($this->originalParameters)
                            ]
                        ]
                    ]
                ],
                'data'      => $this->getAction(
                    route:
                    match ($this->routeName) {
                        'inventory.locations.remove' => [
                                'name' => 'models.location.delete',
                                'parameters' => array_values($this->originalParameters)
                        ],
                        'inventory.warehouses.show.locations.remove' => [
                                'name' => 'models.warehouse.location.delete',
                                'parameters' => array_values($this->originalParameters)
                        ],
                        'inventory.warehouses.show.warehouse-areas.show.locations.remove' => [
                                'name' => 'models.warehouse.warehouse-area.location.delete',
                                'parameters' => array_values($this->originalParameters)
                        ]
                    }
                )
            ]
        );
    }


    public function getBreadcrumbs(array $routeParameters): array
    {
        return ShowLocation::make()->getBreadcrumbs(
            $this->routeName,
            routeParameters: $routeParameters,
            suffix: '('.__('deleting').')'
        );
    }
}
