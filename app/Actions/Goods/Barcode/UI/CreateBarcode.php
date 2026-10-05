<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 12:00:00 Central European Summer Time, Bratislava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Barcode\UI;

use App\Actions\Goods\TradeUnit\GetTradeUnitOptionsForBarcode;
use App\Actions\OrgAction;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateBarcode extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('goods.edit');
    }

    public function asController(ActionRequest $request): Response
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($request);
    }

    public function handle(ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Add barcode'),
                'pageHead'    => [
                    'title'   => __('Add barcode'),
                    'icon'    => [
                        'title' => __('Barcode'),
                        'icon'  => 'fal fa-barcode'
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.trade_units.barcodes.index',
                                'parameters' => []
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('Barcode already printed on the product'),
                            'fields' => [
                                'number'     => [
                                    'type'        => 'input',
                                    'label'       => __('Barcode Number'),
                                    'information' => __('The number printed on the product, e.g. the manufacturer\'s EAN. It is never given to another product.'),
                                    'required'    => true
                                ],
                                'trade_unit' => [
                                    'type'        => 'select',
                                    'label'       => __('Trade Unit'),
                                    'placeholder' => __('Select a trade unit'),
                                    'options'     => GetTradeUnitOptionsForBarcode::run(group()),
                                    'required'    => true,
                                    'mode'        => 'single',
                                    'searchable'  => true
                                ],
                                'note'       => [
                                    'type'  => 'textarea',
                                    'label' => __('Note'),
                                ],
                            ]
                        ]
                    ],
                    'route'     => [
                        'name' => 'grp.models.barcodes.store',
                    ]
                ],
            ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            IndexBarcode::make()->getBreadcrumbs('grp.trade_units.barcodes.index', []),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('adding barcode'),
                    ]
                ]
            ]
        );
    }
}
