<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Apr 2025 13:23:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsObject;

class GetEcomOrderActions
{
    use AsObject;

    public function handle(Order $order, $canEdit = false): array
    {
        $actions = [];

        $generateInvoiceLabel = __('Generate Invoice');



        if ($canEdit) {
            $actions    = match ($order->state) {
                OrderStateEnum::CREATING => [
                   [
                        'type'   => 'buttonGroup',
                        'key'    => 'upload-add',
                        'button' => [
                            [
                                'type'    => 'button',
                                'style'   => 'secondary',
                                'icon'    => ['fal', 'fa-upload'],
                                'label'   => '',
                                'key'     => 'upload',
                                'tooltip' => __('Upload pallets via spreadsheet'),
                            ],
                        ],
                    ],
                   [
                        'type'    => 'button',
                        'style'   => 'secondary',
                        'icon'    => 'fal fa-plus',
                        'key'     => 'add-product',
                        'label'   => __('Add products'),
                        'tooltip' => __('Add products'),
                        'route'   => [
                            'name'       => 'grp.models.order.transaction.store',
                            'parameters' => [
                                'order' => $order->id,
                            ]
                        ]
                    ],
                    ($order->transactions()->count() > 0) ?
                        [
                            'type'    => 'button',
                            'style'   => 'save',
                            'tooltip' => __('Submit'),
                            'label'   => __('Submit'),
                            'key'     => 'action',
                            'route'   => [
                                'method'     => 'patch',
                                'name'       => 'grp.models.order.state.submitted',
                                'parameters' => [
                                    'order' => $order->id
                                ]
                            ]
                        ] : [],
                ],
                OrderStateEnum::SUBMITTED => [
                    [
                        'type'    => 'button',
                        'style'   => 'create',
                        'tooltip' => __('Add a product'),
                        'label'   => __('Add a product'),
                        'key'     => 'add-product',
                         'route'   => [
                            'name'       => 'grp.models.order.transaction.store',
                            'parameters' => [
                                'order' => $order->id,
                            ]
                        ]
                    ],
                    [
                        'type'    => 'button',
                        'style'   => 'save',
                        'tooltip' => __('Send order to Warehouse'),
                        'label'   => __('Send to warehouse'),
                        'key'     => 'action',
                        'route'   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.order.state.in-warehouse',
                            'parameters' => [
                                'order' => $order->id
                            ]
                        ]
                    ]
                ],


                OrderStateEnum::FINALISED => [

                    $order->invoices->count() == 0 ?
                        [
                            'type'    => 'button',
                            'style'   => '',
                            'tooltip' => $generateInvoiceLabel,
                            'label'   => $generateInvoiceLabel,
                            'key'     => 'action',
                            'route'   => [
                                'method'     => 'patch',
                                'name'       => 'grp.models.order.generate_invoice',
                                'parameters' => [
                                    'order' => $order->id
                                ]
                            ]
                        ] : [],
                ],

                OrderStateEnum::DISPATCHED => [

                    $order->invoices->count() == 0 ?
                        [
                            'type'    => 'button',
                            'style'   => '',
                            'tooltip' => $generateInvoiceLabel,
                            'label'   => $generateInvoiceLabel,
                            'key'     => 'action',
                            'route'   => [
                                'method'     => 'patch',
                                'name'       => 'grp.models.order.generate_invoice',
                                'parameters' => [
                                    'order' => $order->id
                                ]
                            ]
                        ] : [],
                    [
                        'type'    => 'button',
                        'style'   => 'save',
                        'icon'    => 'fal fa-plus',
                        'tooltip' => __('Create a replacement'),
                        'label'   => __('Replacement'),
                        'key'     => 'replacement',
                        'route'   => [
                            'method'     => 'get',
                            'name'       => 'grp.org.shops.show.ordering.orders.show.replacement.create',
                            'parameters' => [
                                'organisation' => $order->organisation->slug,
                                'shop'         => $order->shop->slug,
                                'order'        => $order->slug
                            ]
                        ]
                    ],
                ],

                default => []
            };
            $showCancel = true;

            if (in_array($order->state, [
                    OrderStateEnum::CANCELLED,
                    OrderStateEnum::DISPATCHED,
                    OrderStateEnum::FINALISED
                ])
                || $order->invoices()->count() > 0
                || $order->deliveryNotes()->where('state', DeliveryNoteStateEnum::DISPATCHED)->count() > 0) {
                $showCancel = false;
            }

            if ($showCancel) {
                array_unshift(
                    $actions,
                    [
                        'type'  => 'button',
                        'style' => 'cancel',
                        'key'   => 'cancel',
                        'tooltip' => __("Cancel the order. If payment has already been made, the amount will be credited to the customer's balance"),
                        'icon'  => 'fas fa-skull',
                        'label' => __('Cancel'),
                        'route' => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.order.state.cancelled',
                            'parameters' => [
                                'order' => $order->id
                            ]
                        ]
                    ]
                );
            }
        }



        return $actions;
    }
}
