<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jan 2024 15:25:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\PalletDelivery;

use App\Enums\EnumHelperTrait;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum PalletDeliveryStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case SUBMITTED = 'submitted';
    case CONFIRMED = 'confirmed';
    case RECEIVED = 'received';
    case NOT_RECEIVED = 'not_received';
    case BOOKING_IN = 'booking_in';
    case BOOKED_IN = 'booked_in';

    public static function labels($forElements = false): array
    {
        return [
            'in_process' => __('In Process'),
            'submitted' => __('Submitted'),
            'confirmed' => __('Confirmed'),
            'received' => __('Received'),
            'not_received' => __('Not Received'),
            'booking_in' => __('Booking In'),
            'booked_in' => __('Booked In'),
        ];
    }

    public static function stateIcon(): array
    {
        // Icon is imported in resources/js/Composables/Icon/PalletDeliveryStateEnum.ts
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
                'color' => '#AD46FF',
            ],
            'booked_in' => [
                'tooltip' => __('Booked in'),
                'icon' => 'fal fa-check-double',
                'class' => 'text-purple-500',
                'color' => '#AD46FF',
            ],
        ];
    }

    public static function count(
        Organisation|FulfilmentCustomer|Location|Fulfilment|Warehouse|PalletDelivery|Group|RecurringBill $parent,
        $forElements = false
    ): array {
        if ($parent instanceof FulfilmentCustomer) {
            $stats = $parent;
        } elseif ($parent instanceof Group) {
            $stats = $parent->fulfilmentStats;
        } else {
            $stats = $parent->stats;
        }

        return [
            'in_process' => $stats->number_pallet_deliveries_state_in_process,
            'submitted' => $stats->number_pallet_deliveries_state_submitted,
            'confirmed' => $stats->number_pallet_deliveries_state_confirmed,
            'received' => $stats->number_pallet_deliveries_state_received,
            'not_received' => $stats->number_pallet_deliveries_state_not_received,
            'booking_in' => $stats->number_pallet_deliveries_state_booking_in,
            'booked_in' => $stats->number_pallet_deliveries_state_booked_in,
        ];
    }

    public static function notifications(string $reference): array
    {
        return [
            'in_process' => [
                'title' => __('Pallet delivery :reference created', ['reference' => $reference]),
                'subtitle' => __('Pallet delivery has been created'),
            ],
            'submitted' => [
                'title' => __('Pallet delivery :reference submitted', ['reference' => $reference]),
                'subtitle' => __('Pallet delivery has been submitted'),
            ],
            'confirmed' => [
                'title' => __('Pallet delivery :reference confirmed', ['reference' => $reference]),
                'subtitle' => __('Pallet delivery has been confirmed'),
            ],
            'received' => [
                'title' => __('Pallet delivery :reference received', ['reference' => $reference]),
                'subtitle' => __('Pallet delivery has been received'),
            ],
            'not_received' => [
                'title' => __('Pallet delivery :reference not received', ['reference' => $reference]),
                'subtitle' => __('Pallet delivery has not been received'),
            ],
            'booking_in' => [
                'title' => __('Pallet delivery :reference booking in', ['reference' => $reference]),
                'subtitle' => __('Pallet delivery has been booking in'),
            ],
            'booked_in' => [
                'title' => __('Pallet delivery :reference booked in', ['reference' => $reference]),
                'subtitle' => __('Pallet delivery has been booked in'),
            ],
        ];
    }
}
