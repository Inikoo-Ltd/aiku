<?php

namespace App\Actions\Inventory\PickingTrolley\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Models\Inventory\PickingTrolley;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditPickingTrolley extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    public function handle(PickingTrolley $pickingTrolley): PickingTrolley
    {
        return $pickingTrolley;
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, PickingTrolley $pickingTrolley, ActionRequest $request): PickingTrolley
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($pickingTrolley);
    }

    public function htmlResponse(PickingTrolley $pickingTrolley, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Picking trolleys'),
                'breadcrumbs' => ShowPickingTrolley::make()->getBreadcrumbs(
                    routeParameters: $request->route()->originalParameters(),
                    suffix: '('.__('Editing').')'
                ),
                'pageHead'    => [
                    'title'   => $pickingTrolley->code,
                    'icon'    => [
                        'title' => __('Picking trolley'),
                        'icon'  => 'fal fa-shopping-cart'
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        'properties' => [
                            'label'  => __('Properties'),
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('Code'),
                                    'value' => $pickingTrolley->code
                                ],
                            ]
                        ]
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.warehouse.picking_trolleys.update',
                            'parameters' => [
                                'warehouse' => $pickingTrolley->warehouse_id,
                                'pickingTrolley' => $pickingTrolley->id
                            ]
                        ],
                    ]
                ]
            ]
        );
    }
}
