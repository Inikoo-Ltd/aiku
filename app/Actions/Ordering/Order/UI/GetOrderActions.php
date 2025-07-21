<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Apr 2025 13:23:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\Platform;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrderActions
{
    use AsObject;

    public function handle(Order $order, $canEdit = false): array
    {
        $actions = [];

        $generateInvoiceLabel = __('Generate Invoice');

        $platform = $order->platform;
        if (!$platform) {
            $platform = Platform::where('type', PlatformTypeEnum::MANUAL)->first();
        }

        if ($canEdit) {
            $actions    = match ($order->state) {
                OrderStateEnum::CREATING => [
                    $platform && $platform->type == PlatformTypeEnum::MANUAL ? [
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
                    ] : [],
                    $platform && $platform->type == PlatformTypeEnum::MANUAL ? [
                        'type'    => 'button',
                        'style'   => 'secondary',
                        'icon'    => 'fal fa-plus',
                        'key'     => 'add-products',
                        'label'   => __('add products'),
                        'tooltip' => __('Add products'),
                        'route'   => [
                            'name'       => 'grp.models.order.transaction.store',
                            'parameters' => [
                                'order' => $order->id,
                            ]
                        ]
                    ] : [],
                    ($order->transactions()->count() > 0) && $platform && $platform->type == PlatformTypeEnum::MANUAL ?
                        [
                            'type'    => 'button',
                            'style'   => 'save',
                            'tooltip' => __('submit'),
                            'label'   => __('submit'),
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
                        'style'   => 'save',
                        'tooltip' => __('Send to Warehouse'),
                        'label'   => __('send to warehouse'),
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


                OrderStateEnum::FINALISED, OrderStateEnum::DISPATCHED => [

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
                            'tooltip' => __('Create Replacement Delivery Note'),
                            'label'   => __('Replacement'),
                            'key'     => 'replacement',
                            'route'   => [
                                'method'     => 'post',
                                'name'       => 'grp.models.order.replacement_delivery_note.store',
                                'parameters' => [
                                    'order' => $order->id
                                ]
                            ]
                        ]
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
                array_unshift($actions, [
                    'type'  => 'button',
                    'style' => 'cancel',
                    'key'   => 'action',
                    'route' => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.order.state.cancelled',
                        'parameters' => [
                            'order' => $order->id
                        ]
                    ]
                ]);
            }
        }

        return $actions;
    }
}
