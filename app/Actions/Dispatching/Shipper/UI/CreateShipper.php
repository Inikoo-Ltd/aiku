<?php

/*
 * author Arya Permana - Kirin
 * created on 14-05-2025-13h-10m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Shipper\UI;

use App\Actions\OrgAction;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateShipper extends OrgAction
{
    public function handle(Organisation $organisation, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
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
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => match ($request->route()->getName()) {
                                    default                       => preg_replace('/create$/', 'index', $request->route()->getName())
                                },
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' =>
                        [
                            [
                                'title'  => __('contact'),
                                'fields' => [
                                    'code' => [
                                        'type'  => 'input',
                                        'label' => __('code')
                                    ],
                                    'name' => [
                                        'type'  => 'input',
                                        'label' => __('name')
                                    ],
                                    'base_url' => [
                                        'type'  => 'input',
                                        'label' => __('base url')
                                    ],
                                ]
                            ]
                        ],
                    'route'     => [
                        'name'      => 'grp.models.shipper.store',
                        'parameters' => [
                            'organisation' => $organisation->id,
                            ]
                    ]
                ]

            ]
        );
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Response
    {
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($organisation, $request);
    }
    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexShippers::make()->getBreadcrumbs(
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating shipper'),
                    ]
                ]
            ]
        );
    }
}
