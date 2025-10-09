<?php
/*
 * author Arya Permana - Kirin
 * created on 10-07-2025-16h-42m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Box\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateBox extends OrgAction
{
    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $request);
    }

    public function handle(Organisation $parent, ActionRequest $request): Response
    {
        $route = [];

        if ($parent instanceof Organisation) {
            $route = [
                'name'       => 'grp.models.org.boxes.store',
                'parameters' => [
                    $parent->id,
                ]
            ];
        }
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'    => __('new box'),
                'icon'     =>
                    [
                        'icon'  => ['fal', 'fa-box'],
                        'title' => __('Box')
                    ],
                'pageHead' => [
                    'title'        => __('new Box'),
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => str_replace('create', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('New Box'),
                            'fields' => [
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('name'),
                                    'required' => true
                                ],
                                'height' => [
                                    'type'     => 'input',
                                    'label'    => __('height'),
                                    'required' => true
                                ],
                                'width' => [
                                    'type'     => 'input',
                                    'label'    => __('width'),
                                    'required' => true
                                ],
                                'depth' => [
                                    'type'     => 'input',
                                    'label'    => __('depth'),
                                    'required' => true
                                ],
                                'stock' => [
                                    'type'     => 'input',
                                    'label'    => __('stock'),
                                    'required' => true
                                ],
                            ]
                        ]
                    ],
                    'route' => $route,
                ],

            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexBoxes::make()->getBreadcrumbs(
                routeName: 'grp.org.warehouses.show.dispatching.boxes.index',
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating box'),
                    ]
                ]
            ]
        );
    }
}
