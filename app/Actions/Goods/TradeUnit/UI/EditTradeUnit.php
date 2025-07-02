<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:26:52 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Models\Goods\TradeUnit;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditTradeUnit extends OrgAction
{
    use WithGoodsAuthorisation;


    public function handle(TradeUnit $tradeUnit): TradeUnit
    {
        return $tradeUnit;
    }

    public function asController(TradeUnit $tradeUnit, ActionRequest $request): TradeUnit
    {
        $this->initialisationFromGroup(group(), $request);
        return $this->handle($tradeUnit);
    }

    public function htmlResponse(TradeUnit $tradeUnit, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Trade Unit'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $tradeUnit,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($tradeUnit, $request),
                    'next'     => $this->getNext($tradeUnit, $request),
                ],
                'pageHead' => [
                    'title'    => $tradeUnit->name,
                    'icon'     => [
                        'title' => __('Trade Unit'),
                        'icon'  => 'fal fa-atom'
                    ],
                    'actions'  => [
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
                            'title'  => __('edit sku'),
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('code'),
                                    'value' => $tradeUnit->code
                                ],
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $tradeUnit->name
                                ],
                                'description' => [
                                    'type'  => 'textarea',
                                    'label' => __('description'),
                                    'value' => $tradeUnit->description
                                ],
                                'gross_weight' => [
                                    'type'  => 'input',
                                    'label' => __('gross weight'),
                                    'value' => $tradeUnit->gross_weight
                                ],
                            ],
                        ]
                    ],

                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.trade-unit.update',
                            'parameters' => $tradeUnit->id

                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(TradeUnit $tradeUnit, string $routeName, array $routeParameters): array
    {
        return ShowTradeUnit::make()->getBreadcrumbs(
            tradeUnit: $tradeUnit,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '(' . __('Editing') . ')'
        );
    }

    public function getPrevious(TradeUnit $tradeUnit, ActionRequest $request): ?array
    {
        $previous = TradeUnit::where('code', '<', $tradeUnit->code)->orderBy('code', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(TradeUnit $tradeUnit, ActionRequest $request): ?array
    {
        $next = TradeUnit::where('code', '>', $tradeUnit->code)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?TradeUnit $tradeUnit, string $routeName): ?array
    {
        if (!$tradeUnit) {
            return null;
        }


        return [
            'label' => $tradeUnit->name,
            'route' => [
                'name'       => $routeName,
                'parameters' => [
                    'tradeUnit' => $tradeUnit->slug
                ]
            ]
        ];
    }
}
