<?php

/*
 * author Arya Permana - Kirin
 * created on 14-05-2025-13h-10m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Shipper\UI;

use App\Actions\OrgAction;
use App\Models\Dispatching\Shipper;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditShipper extends OrgAction
{
    public function handle(Shipper $shipper, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $shipper,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('new shipper'),
                'pageHead'    => [
                    'title'        => __('new shipper'),
                    'icon'         => [
                        'icon'  => ['fal', 'fa-shipping-fast'],
                        'title' => __('shipper')
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

                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('contact'),
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('code'),
                                    'value' => $shipper->code,
                                ],
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $shipper->name,
                                ],
                                'base_url' => [
                                    'type'  => 'input',
                                    'label' => __('base url'),
                                    'value' => Arr::get($shipper->settings, 'base_url', ''),
                                ],
                            ]
                        ]
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.shipper.update',
                            'parameters' => [
                                'organisation' => $shipper->organisation->id,
                                'shipper'      => $shipper->slug,
                            ]
                        ],
                    ]
                ],

            ]
        );
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, Shipper $shipper, ActionRequest $request): Response
    {
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($shipper, $request);
    }
    public function getBreadcrumbs(Shipper $shipper, string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowShipper::make()->getBreadcrumbs(
                $shipper,
                routeName: $routeName,
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Editing shipper'),
                    ]
                ]
            ]
        );
    }
}
