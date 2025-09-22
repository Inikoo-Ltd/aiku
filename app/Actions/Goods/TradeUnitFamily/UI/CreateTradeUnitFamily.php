<?php

namespace App\Actions\Goods\TradeUnitFamily\UI;

use App\Actions\GrpAction;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateTradeUnitFamily extends GrpAction
{
    /** @noinspection PhpUnusedParameterInspection */
    public function asController(ActionRequest $request): Response
    {
        $this->initialisation(group(), $request);

        return $this->handle($request);
    }

    public function handle(ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('New Trade Unit Family'),
                'pageHead'    => [
                    'title'        => __('new trade family'),
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name' => preg_replace('/create$/', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' =>
                        [
                            [
                                'title'  => __('trade unit family'),
                                'fields' => [
                                    'code' => [
                                        'type'     => 'input',
                                        'label'    => __('code'),
                                        'required' => true
                                    ],
                                    'name' => [
                                        'type'     => 'input',
                                        'label'    => __('name'),
                                        'required' => true
                                    ],
                                    'description' => [
                                        'type'     => 'textarea',
                                        'label'    => __('description'),
                                        'required' => false
                                    ],
                                ]
                            ]
                        ],
                    'route' => [
                        'name' => 'grp.models.trade_unit_family.store',
                        'parameters' => []
                    ]
                ]
            ]
        );
    }



    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexTradeUnitFamilies::make()->getBreadcrumbs(
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'         => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating trade unit family'),
                    ]
                ]
            ]
        );
    }
}
