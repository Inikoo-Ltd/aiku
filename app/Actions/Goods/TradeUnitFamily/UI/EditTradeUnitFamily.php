<?php

namespace App\Actions\Goods\TradeUnitFamily\UI;

use App\Actions\GrpAction;
use App\Models\Goods\TradeUnitFamily;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditTradeUnitFamily extends GrpAction
{
    /** @noinspection PhpUnusedParameterInspection */
    public function asController(TradeUnitFamily $tradeUnitFamily, ActionRequest $request): Response
    {
        $this->initialisation(group(), $request);

        return $this->handle($tradeUnitFamily, $request);
    }

    public function handle(TradeUnitFamily $tradeUnitFamily, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $tradeUnitFamily,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Edit Trade Unit Family'),
                'pageHead'    => [
                    'title'        => __('edit trade family'),
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name' => preg_replace('/edit$/', 'show', $request->route()->getName()),
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
                                        'value'    => $tradeUnitFamily->code
                                    ],
                                    'name' => [
                                        'type'     => 'input',
                                        'label'    => __('name'),
                                        'value'    => $tradeUnitFamily->name
                                    ],
                                    'description' => [
                                        'type'     => 'textarea',
                                        'label'    => __('description'),
                                        'value'    => $tradeUnitFamily->description
                                    ],
                                ]
                            ]
                        ],
                        'args'      => [
                                'updateRoute' => [
                                'name' => 'grp.models.trade_unit_family.update',
                                'parameters' => [
                                    'tradeUnitFamily' => $tradeUnitFamily->id
                                ]
                            ]
                        ],
                ]
            ]
        );
    }



    public function getBreadcrumbs(TradeUnitFamily $tradeUnitFamily, string $routeName, array $routeParameters): array
    {
        return
            ShowTradeUnitFamily::make()->getBreadcrumbs(
                tradeUnitFamily: $tradeUnitFamily,
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
                suffix: '(' . __('Editing') . ')'
            );
    }
}
