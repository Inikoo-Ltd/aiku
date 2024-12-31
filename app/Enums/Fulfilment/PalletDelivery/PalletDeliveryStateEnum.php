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
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;

enum PalletDeliveryStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS   = 'in_process';
    case SUBMITTED    = 'submitted';
    case CONFIRMED    = 'confirmed';
    case RECEIVED     = 'received';
    case NOT_RECEIVED = 'not-received';
    case BOOKING_IN   = 'booking-in';
    case BOOKED_IN    = 'booked-in';

    public static function labels($forElements = false): array
    {
        return [
            'in_process'   => __('In Process'),
            'submitted'    => __('Submitted'),
            'confirmed'    => __('Confirmed'),
            'received'     => __('Received'),
            'not-received' => __('Not Received'),
            'booking-in'   => __('Booking In'),
            'booked-in'    => __('Booked In')
        ];
    }

    public static function stateIcon(): array
    {
        // Icon is imported in resources/js/Composables/Icon/PalletDeliveryStateEnum.ts
        return [
            'in_process'   => [
                'tooltip' => __('In process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-lime-500',  // Color for normal icon (Aiku)
                'color'   => 'lime',  // Color for box (Retina)
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5'
                ]
            ],
            'submitted'    => [
                'tooltip' => __('Submitted'),
                'icon'    => 'fal fa-share',
                'class'   => 'text-indigo-400',
                'color'   => 'indigo',
                'app'     => [
                    'name' => 'share',
                    'type' => 'font-awesome-5'
                ]
            ],
            'confirmed'    => [
                'tooltip' => __('Confirmed'),
                'icon'    => 'fal fa-spell-check',
                'class'   => 'text-emerald-500',
                'color'   => 'emerald',
                'app'     => [
                    'name' => 'spell-check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'received'     => [
                'tooltip' => __('Received'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-slate-500',
                'color'   => 'slate',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'not-received' => [
                'tooltip' => __('Not Received'),
                'icon'    => 'fal fa-times',
                'class'   => 'text-red-500',
                'color'   => 'slate',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],
            'booking-in'   => [
                'tooltip' => __('Booking in'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-purple-500',
                'color'   => 'purple',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'booked-in'    => [
                'tooltip' => __('Booked in'),
                'icon'    => 'fal fa-check-double',
                'class'   => 'text-purple-500',
                'color'   => 'purple',
                'app'     => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5'
                ]
            ],
        ];
    }

    public static function count(
        Organisation|FulfilmentCustomer|Location|Fulfilment|Warehouse|PalletDelivery $parent,
        $forElements = false
    ): array {
        if ($parent instanceof FulfilmentCustomer) {
            $stats = $parent;
        } else {
            $stats = $parent->stats;
        }

        return [
            'in_process'   => $stats->number_pallet_deliveries_state_in_process,
            'submitted'    => $stats->number_pallet_deliveries_state_submitted,
            'confirmed'    => $stats->number_pallet_deliveries_state_confirmed,
            'received'     => $stats->number_pallet_deliveries_state_received,
            'not-received' => $stats->number_pallet_deliveries_state_not_received,
            'booking-in'   => $stats->number_pallet_deliveries_state_booking_in,
            'booked-in'    => $stats->number_pallet_deliveries_state_booked_in,
        ];
    }

    public static function notifications(string $reference): array
    {
        return [
            'in_process'   => [
                'title'    => __("Pallet delivery :reference created", ['reference' => $reference]),
                'subtitle' => __('Pallet delivery has been created')
            ],
            'submitted'    => [
                'title'    => __("Pallet delivery :reference submitted", ['reference' => $reference]),
                'subtitle' => __('Pallet delivery has been submitted')
            ],
            'confirmed'    => [
                'title'    => __("Pallet delivery :reference confirmed", ['reference' => $reference]),
                'subtitle' => __('Pallet delivery has been confirmed')
            ],
            'received'     => [
                'title'    => __("Pallet delivery :reference received", ['reference' => $reference]),
                'subtitle' => __('Pallet delivery has been received')
            ],
            'not-received' => [
                'title'    => __("Pallet delivery :reference not received", ['reference' => $reference]),
                'subtitle' => __('Pallet delivery has not been received')
            ],
            'booking-in'   => [
                'title'    => __("Pallet delivery :reference booking in", ['reference' => $reference]),
                'subtitle' => __('Pallet delivery has been booking in')
            ],
            'booked-in'    => [
                'title'    => __("Pallet delivery :reference booked in", ['reference' => $reference]),
                'subtitle' => __('Pallet delivery has been booked in')
            ],
        ];
    }
}
