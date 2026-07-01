<?php

/*
 * Author Louis Perez
 * Created on 29-06-2026-14h-17m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\Barcode\UI;

use App\Actions\Goods\TradeUnit\GetTradeUnitOptionsForBarcode;
use App\Actions\Goods\TradeUnit\UI\ShowTradeUnitsDashboard;
use App\Actions\GrpAction;
use App\Models\Helpers\Barcode;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditBarcode extends GrpAction
{
    public function asController(Barcode $barcode, ActionRequest $request): Barcode
    {
        $this->initialisation(group(), $request);

        return $this->handle($barcode);
    }

    public function handle(Barcode $barcode): Barcode
    {
        return $barcode->load('tradeUnitsActive');
    }

    public function htmlResponse(Barcode $barcode, ActionRequest $request): Response
    {
        $activeTradeUnit = $barcode->tradeUnitsActive->first();
        $tradeUnitOptions = GetTradeUnitOptionsForBarcode::run(group());

        if ($activeTradeUnit) {
            data_set($tradeUnitOptions, $activeTradeUnit->id, [
                'label'     => $activeTradeUnit->code." - ".$activeTradeUnit->name,
                'id'        => $activeTradeUnit->id,
            ]);
        }

        return Inertia::render('EditModel', [
            'title'            => __('Barcode'),
            'breadcrumbs'      => $this->getBreadcrumbs(
                $barcode,
                $request->route()->getName(),
                $request->route()->originalParameters(),
                '('.__('Editing').')'
            ),
            // 'navigation'       => [
            //     'previous' => $this->getPreviousModel($family, $request),
            //     'next'     => $this->getNextModel($family, $request),
            // ],
            'pageHead'         => [
                'title'     => $barcode->number,
                'icon'      => [
                    'icon'  => ['fal', 'fa-barcode'],
                    'title' => __('Barcode')
                ],
                'iconRight'  => $barcode->status->icon(),
                'actions'   => [
                    [
                        'type'  => 'button',
                        'style' => 'exitEdit',
                        'route' => [
                            'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters())
                        ]
                    ]
                ],
            ],
            'formData' => [
                'blueprint' => [
                    [
                        'label'  => __('Properties'),
                        'icon'   => 'fa-light fa-fingerprint',
                        'fields' => [
                            'number' => [
                                'type' => 'input',
                                'label' => __('Barcode Number'),
                                'value' => $barcode->number
                            ],
                            'note' => [
                                'type'     => 'textarea',
                                'label'    => __('Note'),
                                'value'    => $barcode->note
                            ],
                        ],
                    ],
                    [
                        'label'  => __('Trade Unit'),
                        'icon'   => 'fa-light fa-atom',
                        'fields' => [
                            'trade_unit'  => [
                                'type'        => 'select',
                                'label'       => __('Trade Unit'),
                                'placeholder' => __('Select a trade unit'),
                                'options'     => $tradeUnitOptions,
                                'value'       => $activeTradeUnit?->id,
                                'required'    => false,
                                'mode'        => 'single',
                                'searchable'  => true
                            ],
                        ]
                    ]
                ],
                'args'      => [
                    'updateRoute' => [
                        'name'       => 'grp.models.barcodes.update',
                        'parameters' => [
                            'barcode'   => $barcode->id
                        ],
                    ]
                ],
            ]
        ]);
    }

    public function getBreadcrumbs(Barcode $barcode, string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (Barcode $barcode, array $routeParameters, $suffix, $suffixIndex = '', $prefixIndex = '') {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => $prefixIndex.__('Barcodes').$suffixIndex,
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $barcode->number,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ];
        };

        return array_merge(
            ShowTradeUnitsDashboard::make()->getBreadcrumbs(),
            $headCrumb(
                $barcode,
                [
                    'index' => [
                        'name'       => 'grp.trade_units.barcodes.index',
                        'parameters' => Arr::except($routeParameters, 'barcode')
                    ],
                    'model' => [
                        'name'       => 'grp.trade_units.barcodes.show',
                        'parameters' => $routeParameters
                    ]
                ],
                $suffix
            )
        );
    }
}
