<?php

namespace App\Actions\Inventory\PickedBay\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreatePickedBay extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    public function handle(Warehouse $warehouse, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('create picked bay'),
                'pageHead'    => [
                    'model'   => __('Picked bay'),
                    'title'   => __('Create'),
                    'icon'    => 'fal fa-monument',
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => preg_replace('/create$/', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('ID/Name'),
                            'fields' => [
                                'code' => [
                                    'type'        => 'input',
                                    'label'       => __('Code'),
                                    'placeholder' => __('maximum 64 character long'),
                                    'value'       => '',
                                    'required'    => true,
                                ],
                            ]
                        ],
                    ],
                    'route'     => [
                        'name'       => 'grp.models.warehouse.picked_bays.store',
                        'parameters' => $warehouse->id
                    ]
                ],
            ]
        );
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Response
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexPickedBays::make()->getBreadcrumbs(
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating picked bay'),
                    ]
                ]
            ]
        );
    }
}
