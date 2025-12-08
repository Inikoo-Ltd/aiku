<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\InertiaAction;
use App\Models\Procurement\PurchaseOrder;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditPurchaseOrder extends InertiaAction
{
    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $purchaseOrder;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo('procurement.edit');

        return $request->user()->authTo('procurement.view');
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($request);

        return $this->handle($purchaseOrder);
    }

    public function htmlResponse(PurchaseOrder $purchaseOrder): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title' => __('purchase order'),
                'pageHead' => [
                    'title' => $purchaseOrder->reference,
                    'actions' => [
                        [
                            'type' => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name' => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],

                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'title' => __('id'),
                            'fields' => [
                                'number' => [
                                    'type' => 'input',
                                    'label' => __('number'),
                                    'value' => $purchaseOrder->number,
                                ],
                            ],
                        ],

                    ],
                    'args' => [
                        'updateRoute' => [
                            'name' => 'grp.models.purchase-order.update',
                            'parameters' => $purchaseOrder->slug,

                        ],
                    ],
                ],
            ]
        );
    }
}
