<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 03:02:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\Pallet;

use App\Enums\EnumHelperTrait;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;

enum PalletStateEnum: string
{
    use EnumHelperTrait;

    // Status: in_process
    case IN_PROCESS = 'in_process';

    // Status: receiving
    case SUBMITTED = 'submitted';
    case CONFIRMED = 'confirmed';
    case RECEIVED = 'received';
    case BOOKING_IN = 'booking_in';
    case BOOKED_IN = 'booked_in';

    // Status: not_received
    case NOT_RECEIVED = 'not_received';

    // Status: storing
    case STORING = 'storing';

    // Status: returning
    case REQUEST_RETURN_IN_PROCESS = 'request_return_in_process';
    case REQUEST_RETURN_SUBMITTED = 'request_return_submitted';
    case REQUEST_RETURN_CONFIRMED = 'request_return_confirmed';
    case PICKING = 'picking';
    case PICKED = 'picked';

    // Status: incident
    case DAMAGED = 'damaged';
    case LOST = 'lost';
    case OTHER_INCIDENT = 'other_incident';

    // Status: returned
    case DISPATCHED = 'dispatched';

    public static function labels(): array
    {
        return [
            'in_process' => __('In process'),
            'submitted' => __('Submitted'),
            'confirmed' => __('Confirmed'),
            'not_received' => __('Not Received'),
            'received' => __('Received'),
            'booking_in' => __('Booking in'),
            'booked_in' => __('Booked in'),
            'storing' => __('Storing'),
            'request_return_in_process' => __('Request Return'),
            'request_return_submitted' => __('Request Return').' ('.__('Submitted').')',
            'request_return_confirmed' => __('Request Return').' ('.__('Confirmed').')',
            'picking' => __('Picking'),
            'picked' => __('Picked'),
            'dispatched' => __('Dispatched'),
            'lost' => __('Lost'),
            'damaged' => __('Damaged'),
            'other_incident' => __('Other'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [
                'tooltip' => __('In process'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-lime-500',  // Color for normal icon (Aiku)
                'color' => '#7CCE00',  // Color for box (Retina)
            ],
            'submitted' => [
                'tooltip' => __('Submitted'),
                'icon' => 'fal fa-share',
                'class' => 'text-indigo-400',
                'color' => '#7C86FF',
            ],
            'confirmed' => [
                'tooltip' => __('Confirmed'),
                'icon' => 'fal fa-spell-check',
                'class' => 'text-emerald-500',
                'color' => '#00BC7D',
            ],
            'received' => [
                'tooltip' => __('Received'),
                'icon' => 'fal fa-check',
                'class' => 'text-slate-500',
                'color' => '#62748E',
            ],
            'not_received' => [
                'tooltip' => __('Not Received'),
                'icon' => 'fal fa-times',
                'class' => 'text-red-500',
                'color' => '#FB2C36',
            ],
            'booking_in' => [
                'tooltip' => __('Booking in'),
                'icon' => 'fal fa-check',
                'class' => 'text-purple-500',
                'color' => 'purple',
            ],
            'booked_in' => [
                'tooltip' => __('Booked in'),
                'icon' => 'fal fa-check-double',
                'class' => 'text-purple-300',
                'color' => '#C27AFF',
            ],
            'storing' => [
                'tooltip' => __('Storing'),
                'icon' => 'fal fa-warehouse-alt',
                'class' => 'text-purple-500',
                'color' => '#C27AFF',
            ],

            'dispatched' => [
                'tooltip' => __('Dispatched'),
                'icon' => 'fal fa-sign-out-alt',
                'class' => 'text-gray-400',
                'color' => '#99A1AF',
            ],
            'request_return_in_process' => [
                'tooltip' => __('Request Return'),
                'icon' => 'fal fa-sign-out-alt',
                'class' => 'text-green-600',
                'color' => '#00A63E',
            ],
            'request_return_submitted' => [
                'tooltip' => __('Request Return').' ('.__('Submitted').')',
                'icon' => 'fal fa-sign-out-alt',
                'class' => 'text-green-600',
                'color' => '#00A63E',
            ],
            'request_return_confirmed' => [
                'tooltip' => __('Request Return').' ('.__('Confirmed').')',
                'icon' => 'fal fa-sign-out-alt',
                'class' => 'text-green-600',
                'color' => '#00A63E',
            ],
            'picking' => [
                'tooltip' => __('Picking'),
                'icon' => 'fal fa-check',
                'class' => 'text-green-400',
                'color' => '#05DF72',
            ],
            'picked' => [
                'tooltip' => __('Picked'),
                'icon' => 'fal fa-check',
                'class' => 'text-green-400',
                'color' => '#05DF72',
            ],
            'damaged' => [
                'tooltip' => __('Damaged'),
                'icon' => 'fal fa-fragile',
                'class' => 'text-red-400',
                'color' => '#FF6467',
            ],
            'lost' => [
                'tooltip' => __('Lost'),
                'icon' => 'fal fa-ghost',
                'class' => 'text-red-400',
                'color' => '#FF6467',
            ],
            'other_incident' => [
                'tooltip' => __('Other incident'),
                'icon' => 'fal fa-ghost',
                'class' => 'text-red-400',
                'color' => '#FF6467',
            ],
        ];
    }

    public static function count(
        Organisation|FulfilmentCustomer|Fulfilment|Warehouse|PalletDelivery|PalletReturn $parent
    ): array {
        if ($parent instanceof FulfilmentCustomer) {
            $stats = $parent;
        } elseif ($parent instanceof Organisation) {
            $stats = $parent->fulfilmentStats;
        } else {
            $stats = $parent->stats;
        }

        return [
            'in_process' => $stats->number_pallets_state_in_process,
            'submitted' => $stats->number_pallets_state_submitted,
            'confirmed' => $stats->number_pallets_state_confirmed,
            'not_received' => $stats->number_pallets_state_not_received,
            'received' => $stats->number_pallets_state_received,
            'booking_in' => $stats->number_pallets_state_booking_in,
            'booked_in' => $stats->number_pallets_state_booked_in,
            'storing' => $stats->number_pallets_state_storing,
            'request_return' => $stats->number_pallets_state_request_return,
            'picking' => $stats->number_pallets_state_picking,
            'picked' => $stats->number_pallets_state_picked,
            'dispatched' => $stats->number_pallets_state_disoatched,
            'lost' => $stats->number_pallets_state_lost,
            'damaged' => $stats->number_pallets_state_damaged,
            'other_incident' => $stats->number_pallets_state_other,
        ];
    }
}
