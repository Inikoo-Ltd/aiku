<?php

namespace App\Actions\Inventory\PickedBay\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Models\Inventory\PickedBay;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditPickedBay extends OrgAction
{
    use WithWarehouseEditAuthorisation;
    public function handle(PickedBay $pickedBay): PickedBay
    {
        return $pickedBay;
    }
    public function asController(Organisation $organisation, Warehouse $warehouse, PickedBay $pickedBay, ActionRequest $request): PickedBay
    {
        $this->initialisationFromWarehouse($warehouse, $request);
        return $this->handle($pickedBay);
    }
    public function htmlResponse(PickedBay $pickedBay, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Picked bays'),
                'breadcrumbs' => ShowPickedBay::make()->getBreadcrumbs(
                    routeName: $request->route()->getName(),
                    routeParameters: $request->route()->originalParameters(),
                    suffix: '('.__('Editing').')'
                ),
                'pageHead'    => [
                    'model' => __('Edit Picked bay'),
                    'title'   => $pickedBay->code,
                    'icon'    => [
                        'title' => __('Picked bay'),
                        'icon'  => 'fal fa-monument'
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
                        [
                            'label'  => __('Properties'),
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('Code'),
                                    'value' => $pickedBay->code
                                ],
                            ]
                        ]
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.warehouse.picked_bays.update',
                            'parameters' => [
                                'warehouse' => $pickedBay->warehouse_id,
                                'pickedBay' => $pickedBay->id
                            ]
                        ],
                    ]
                ]
            ]
        );
    }
}
