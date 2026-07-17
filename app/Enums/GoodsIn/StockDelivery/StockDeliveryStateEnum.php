<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 May 2023 14:50:49 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\GoodsIn\StockDelivery;

use App\Enums\EnumHelperTrait;

enum StockDeliveryStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case CONFIRMED = 'confirmed';
    case READY_TO_SHIP = 'ready_to_ship';
    case DISPATCHED = 'dispatched';
    case RECEIVED = 'received';
    case CHECKED = 'checked';
    case BOOKING_IN = 'booking_in';
    case BOOKED_IN = 'booked_in';
    case PLACED = 'placed';
    case CANCELLED = 'cancelled';
    case NOT_RECEIVED = 'not_received';

    public static function labels(): array
    {
        return [
            'in_process'    => __('In process'),
            'confirmed'     => __('Confirmed'),
            'ready_to_ship' => __('Ready to ship'),
            'dispatched'    => __('Dispatched'),
            'received'      => __('Received'),
            'checked'       => __('Checked'),
            'booking_in'    => __('Booking in'),
            'booked_in'     => __('Booked in'),
            'placed'        => __('Placed'),
            'cancelled'     => __('Cancelled'),
            'not_received'  => __('Not Received'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process'    => [
                'tooltip' => __('In process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-lime-500',
                'color'   => 'lime',
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5'
                ]
            ],
            'confirmed'     => [
                'tooltip' => __('Confirmed'),
                'icon'    => 'fal fa-spell-check',
                'class'   => 'text-emerald-500',
                'color'   => 'emerald',
                'app'     => [
                    'name' => 'spell-check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'ready_to_ship' => [
                'tooltip' => __('Ready to ship'),
                'icon'    => 'fal fa-box-check',
                'class'   => 'text-indigo-400',
                'color'   => 'indigo',
                'app'     => [
                    'name' => 'box-check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'dispatched'    => [
                'tooltip' => __('Dispatched'),
                'icon'    => 'fal fa-truck',
                'class'   => 'text-indigo-500',
                'color'   => 'indigo',
                'app'     => [
                    'name' => 'truck',
                    'type' => 'font-awesome-5'
                ]
            ],
            'received'      => [
                'tooltip' => __('Received'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-slate-500',
                'color'   => 'slate',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'checked'       => [
                'tooltip' => __('Checked'),
                'icon'    => 'fal fa-clipboard-check',
                'class'   => 'text-slate-500',
                'color'   => 'slate',
                'app'     => [
                    'name' => 'clipboard-check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'booking_in'    => [
                'tooltip' => __('Booking in'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-purple-500',
                'color'   => 'purple',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'booked_in'     => [
                'tooltip' => __('Booked in'),
                'icon'    => 'fal fa-check-double',
                'class'   => 'text-purple-500',
                'color'   => 'purple',
                'app'     => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5'
                ]
            ],
            'placed'        => [
                'tooltip' => __('Placed'),
                'icon'    => 'fal fa-check-double',
                'class'   => 'text-success-500',
                'color'   => 'success',
                'app'     => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5'
                ]
            ],
            'cancelled'     => [
                'tooltip' => __('Cancelled'),
                'icon'    => 'fal fa-times-circle',
                'class'   => 'text-danger-500',
                'color'   => 'danger',
                'app'     => [
                    'name' => 'times-circle',
                    'type' => 'font-awesome-5'
                ]
            ],
            'not_received'  => [
                'tooltip' => __('Not Received'),
                'icon'    => 'fal fa-exclamation-circle',
                'class'   => 'text-warning-500',
                'color'   => 'warning',
                'app'     => [
                    'name' => 'exclamation-circle',
                    'type' => 'font-awesome-5'
                ]
            ],
        ];
    }
}
