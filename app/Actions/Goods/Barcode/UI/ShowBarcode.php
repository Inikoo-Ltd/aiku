<?php

/*
 * Author Louis Perez
 * Created on 22-06-2026-13h-39m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\Barcode\UI;

use App\Actions\Goods\TradeUnit\UI\ShowTradeUnitsDashboard;
use App\Actions\GrpAction;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Enums\UI\Goods\BarcodeTabsEnum;
use App\Http\Resources\Goods\BarcodeResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Helpers\Barcode;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowBarcode extends GrpAction
{
    public function asController(Barcode $barcode, ActionRequest $request): Barcode
    {
        $this->initialisation(group(), $request)->withTab(BarcodeTabsEnum::values());

        return $this->handle($barcode);
    }

    public function jsonResponse(Barcode $barcode): array
    {
        return BarcodeResource::make($barcode)->resolve();
    }

    public function handle(Barcode $barcode): Barcode
    {
        return $barcode->load('tradeUnitActive');
    }

    public function htmlResponse(Barcode $barcode, ActionRequest $request): Response
    {
        return Inertia::render('Goods/Barcode', [
            'title'            => __('Barcode'),
            'breadcrumbs'      => $this->getBreadcrumbs(
                $barcode,
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            // 'navigation'       => [
            //     'previous' => $this->getPreviousModel($family, $request),
            //     'next'     => $this->getNextModel($family, $request),
            // ],
            'pageHead'         => [
                'title'     => $barcode->number,
                'model'     => __('Barcode'),
                'icon'      => [
                    'icon'  => ['fal', 'fa-barcode'],
                    'title' => __('Barcode')
                ],
                'iconRight'  => $barcode->status->icon(),
                'actions'    => [

                ],
            ],
            'tabs'             => [
                'current'    => $this->tab,
                'navigation' => BarcodeTabsEnum::navigation(),
            ],

            BarcodeTabsEnum::SHOWCASE->value => $this->tab == BarcodeTabsEnum::SHOWCASE->value ?
                fn () => $this->jsonResponse($barcode) :
                Inertia::lazy(fn () => $this->jsonResponse($barcode)),

            BarcodeTabsEnum::HISTORY->value => $this->tab == BarcodeTabsEnum::HISTORY->value ?
                fn () => HistoryResource::collection(IndexHistory::run($barcode))
                : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($barcode))),
        ])
        ->table(IndexHistory::make()->tableStructure(prefix: BarcodeTabsEnum::HISTORY->value));
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
