<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditPurchaseOrder extends OrgAction
{
    use WithProcurementEditAuthorisation;

    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $purchaseOrder;
    }

    public function asController(Organisation $organisation, PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($organisation, $request);

        return $this->handle($purchaseOrder);
    }

    public function htmlResponse(PurchaseOrder $purchaseOrder): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('purchase order'),
                'pageHead'    => [
                    'title'     => $purchaseOrder->reference,
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.org.procurement.purchase_orders.show',
                                'parameters' => [$purchaseOrder->organisation->slug, $purchaseOrder->slug]
                            ]
                        ]
                    ],
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('id'),
                            'fields' => [
                                'reference' => [
                                    'type'     => 'input',
                                    'label'    => __('reference'),
                                    'required' => true,
                                    'value'    => $purchaseOrder->reference
                                ],
                            ]
                        ],
                        [
                            'title'  => __('Delivery'),
                            'icon'   => 'fal fa-truck',
                            'fields' => [
                                'delivery_address' => [
                                    'type'        => 'textarea',
                                    'label'       => __('Deliver to'),
                                    'placeholder' => __('Leave empty to use the warehouse address'),
                                    'value'       => Arr::get($purchaseOrder->data, 'delivery_address'),
                                ],
                            ]
                        ],
                        [
                            'title'  => __('Payments'),
                            'icon'   => 'fal fa-money-bill',
                            'fields' => [
                                'deposit_amount' => [
                                    'type'  => 'input',
                                    'label' => __('Deposit amount'),
                                    'value' => $purchaseOrder->deposit_amount,
                                ],
                                'deposit_paid_at' => [
                                    'type'  => 'date',
                                    'label' => __('Deposit paid'),
                                    'value' => $purchaseOrder->deposit_paid_at,
                                ],
                                'balance_paid_at' => [
                                    'type'  => 'date',
                                    'label' => __('Balance paid'),
                                    'value' => $purchaseOrder->balance_paid_at,
                                ],
                            ]
                        ]

                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'      => 'grp.models.purchase-order.update',
                            'parameters' => $purchaseOrder->id

                        ],
                    ]
                ]
            ]
        );
    }
}
