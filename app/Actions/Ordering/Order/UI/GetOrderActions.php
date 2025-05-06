<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Apr 2025 13:23:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

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

        $platform  = $order->platform;
        if (!$platform) {
            $platform = Platform::where('type', PlatformTypeEnum::MANUAL)->first();
        }

        if ($canEdit) {
            $actions = match ($order->state) {
                OrderStateEnum::CREATING => [
                    $platform && $platform->type == PlatformTypeEnum::MANUAL ? [
                        'type' => 'buttonGroup',
                        'key' => 'upload-add',
                        'button' => [
                            [
                                'type' => 'button',
                                'style' => 'secondary',
                                'icon' => ['fal', 'fa-upload'],
                                'label' => '',
                                'key' => 'upload',
                                'tooltip' => __('Upload pallets via spreadsheet'),
                            ],
                        ],
                    ] : [],
                    $platform && $platform->type == PlatformTypeEnum::MANUAL ? [
                        'type' => 'button',
                        'style' => 'secondary',
                        'icon' => 'fal fa-plus',
                        'key' => 'add-products',
                        'label' => __('add products'),
                        'tooltip' => __('Add products'),
                        'route' => [
                            'name' => 'grp.models.order.transaction.store',
                            'parameters' => [
                                'order' => $order->id,
                            ]
                        ]
                    ] : [],
                    ($order->transactions()->count() > 0) && $platform && $platform->type == PlatformTypeEnum::MANUAL ?
                        [
                            'type' => 'button',
                            'style' => 'save',
                            'tooltip' => __('submit'),
                            'label' => __('submit'),
                            'key' => 'action',
                            'route' => [
                                'method' => 'patch',
                                'name' => 'grp.models.order.state.submitted',
                                'parameters' => [
                                    'order' => $order->id
                                ]
                            ]
                        ] : [],
                ],
                OrderStateEnum::SUBMITTED => [
                    [
                        'type' => 'button',
                        'style' => 'save',
                        'tooltip' => __('Send to Warehouse'),
                        'label' => __('send to warehouse'),
                        'key' => 'action',
                        'route' => [
                            'method' => 'patch',
                            'name' => 'grp.models.order.state.in-warehouse',
                            'parameters' => [
                                'order' => $order->id
                            ]
                        ]
                    ]
                ],
                OrderStateEnum::IN_WAREHOUSE => [
                    [
                        'type' => 'button',
                        'style' => 'save',
                        'tooltip' => __('Handle'),
                        'label' => __('Handle'),
                        'key' => 'action',
                        'route' => [
                            'method' => 'patch',
                            'name' => 'grp.models.order.state.handling',
                            'parameters' => [
                                'order' => $order->id
                            ]
                        ]
                    ]
                ],
                OrderStateEnum::HANDLING => [
                    [
                        'type' => 'button',
                        'style' => 'save',
                        'tooltip' => __('Pack'),
                        'label' => __('Pack'),
                        'key' => 'action',
                        'route' => [
                            'method' => 'patch',
                            'name' => 'grp.models.order.state.packed',
                            'parameters' => [
                                'order' => $order->id
                            ]
                        ]
                    ]
                ],
                OrderStateEnum::PACKED => [
                    [
                        'type' => 'button',
                        'style' => 'save',
                        'tooltip' => __('Finalize'),
                        'label' => __('Finalize'),
                        'key' => 'action',
                        'route' => [
                            'method' => 'patch',
                            'name' => 'grp.models.order.state.finalized',
                            'parameters' => [
                                'order' => $order->id
                            ]
                        ]
                    ]
                ],
                OrderStateEnum::FINALISED => [
                    [
                        'type' => 'button',
                        'style' => 'save',
                        'tooltip' => __('Dispatch'),
                        'label' => __('Dispatch'),
                        'key' => 'action',
                        'route' => [
                            'method' => 'patch',
                            'name' => 'grp.models.order.state.dispatched',
                            'parameters' => [
                                'order' => $order->id
                            ]
                        ]
                    ]
                ],
                default => []
            };
        }

        return $actions;
    }

}
