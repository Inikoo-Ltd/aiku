<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:24:56 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\Order;

use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum OrderStateEnum: string
{
    use EnumHelperTrait;

    case CREATING = 'creating';
    case SUBMITTED = 'submitted';
    case IN_WAREHOUSE = 'in_warehouse'; // Waiting to be picked
    case HANDLING = 'handling';  // Being picked
    case HANDLING_BLOCKED = 'handling_blocked';  // Being picked
    case PACKED = 'packed';  // Packed and ready to be dispatched
    case FINALISED = 'finalised';  // Invoiced and ready to be dispatched
    case DISPATCHED = 'dispatched';
    case CANCELLED = 'cancelled';

    public static function labels(): array
    {
        return [
            'creating'         => __('Creating'),
            'submitted'        => __('Submitted'),
            'in_warehouse'     => __('In Warehouse'),
            'handling'         => __('Handling'),
            'handling_blocked' => __('Handling Blocked'),
            'packed'           => __('Packed'),
            'finalised'        => __('Finalized'),
            'dispatched'       => __('Dispatched'),
            'cancelled'        => __('Cancelled'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'creating'         => [
                'tooltip' => __('Creating'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-lime-500',  // Color for normal icon (Aiku)
                'color'   => 'lime',  // Color for box (Retina)
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5'
                ]
            ],
            'submitted'        => [
                'tooltip' => __('Submitted'),
                'icon'    => 'fal fa-paper-plane',
                'class'   => 'text-indigo-400',
                'color'   => 'indigo',
                'app'     => [
                    'name' => 'share',
                    'type' => 'font-awesome-5'
                ]
            ],
            'in_warehouse'     => [
                'tooltip' => __('In Warehouse'),
                'icon'    => 'fal fa-warehouse',
                'class'   => 'text-gray-500',
                'color'   => 'emerald',
                'app'     => [
                    'name' => 'spell-check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'handling'         => [
                'tooltip' => __('Handling'),
                'icon'    => 'fal fa-hands-helping',
                'class'   => 'text-gray-500',
                'color'   => 'slate',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'handling_blocked' => [
                'tooltip' => __('Handling Blocked'),
                'icon'    => 'fal fa-hands-helping',
                'class'   => 'text-gray-500',
                'color'   => 'slate',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],
            'packed'           => [
                'tooltip' => __('Packed'),
                'icon'    => 'fal fa-box',
                'class'   => 'text-gray-500',
                'color'   => 'slate',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],
            'finalised'        => [
                'tooltip' => __('Finalized'),
                'icon'    => 'fal fa-tasks',
                'class'   => 'text-gray-500',
                'color'   => 'purple',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'dispatched'       => [
                'tooltip' => __('Dispatched'),
                'icon'    => 'fal fa-shipping-fast',
                'class'   => 'text-gray-500',
                'color'   => 'purple',
                'app'     => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5'
                ]
            ],
            'cancelled'        => [
                'tooltip' => __('Cancelled'),
                'icon'    => 'fal fa-times-circle',
                'class'   => 'text-red-500',
                'color'   => 'purple',
                'app'     => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5'
                ]
            ],
        ];
    }

    public static function count(Group|Organisation|Shop|Customer|CustomerClient|Asset $parent): array
    {
        if ($parent instanceof Organisation || $parent instanceof Shop || $parent instanceof Group) {
            $stats = $parent->orderingStats;
        } elseif ($parent instanceof CustomerClient) {
            $stats = $parent->customer->stats;
        } else {
            $stats = $parent->stats;
        }

        return [
            'creating'         => $stats->number_orders_state_creating,
            'submitted'        => $stats->number_orders_state_submitted,
            'in_warehouse'     => $stats->number_orders_state_in_warehouse,
            'handling'         => $stats->number_orders_state_handling,
            'handling_blocked' => $stats->number_orders_state_handling_blocked,
            'packed'           => $stats->number_orders_state_packed,
            'finalised'        => $stats->number_orders_state_finalised,
            'dispatched'       => $stats->number_orders_state_dispatched,
            'cancelled'        => $stats->number_orders_state_cancelled,
        ];
    }
}
