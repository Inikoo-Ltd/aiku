<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jan 2024 15:25:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\PalletReturn;

use App\Enums\EnumHelperTrait;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;

enum PalletReturnStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case SUBMITTED = 'submitted';
    case CONFIRMED = 'confirmed';
    case PICKING = 'picking';
    case PICKED = 'picked';
    case DISPATCHED = 'dispatched';
    // case CONSOLIDATED = 'consolidated';
    case CANCEL = 'cancel';

    public static function labels($forElements = false): array
    {
        return [
            'in_process' => __('In Process'),
            'submitted' => __('Submitted'),
            'confirmed' => __('Confirmed'),
            'picking' => __('Picking'),
            'picked' => __('Picked'),
            'dispatched' => __('Dispatched'),
            'cancel' => __('Cancel'),
            // 'consolidated' => __('Consolidated')
        ];
    }

    public static function stateIcon(): array
    {
        // Icon is imported in resources/js/Composables/Icon/PalletReturnStateEnum.ts
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
            'picking' => [
                'tooltip' => __('Picking'),
                'icon' => 'fal fa-truck',
                'class' => 'text-orange-500',
                'color' => '#FF6900',
            ],
            'picked' => [
                'tooltip' => __('Picked'),
                'icon' => 'fal fa-check',
                'class' => 'text-slate-500',
                'color' => '#62748E',
            ],
            'dispatched' => [
                'tooltip' => __('Dispatched'),
                'icon' => 'fal fa-check-double',
                'class' => 'text-purple-500',
                'color' => '#AD46FF',
            ],
            'cancel' => [
                'tooltip' => __('Cancel'),
                'icon' => 'fal fa-times',
                'class' => 'text-red-500',
                'color' => '#FB2C36',
            ],
            // 'consolidated'     => [
            //     'tooltip' => __('Consolidated'),
            //     'icon'    => 'fal fa-times',
            //     'class'   => 'text-red-500',
            //     'color'   => 'red',
            //     'app'     => [
            //         'name' => 'times',
            //         'type' => 'font-awesome-5'
            //     ]
            // ],
        ];
    }

    public static function count(Organisation|FulfilmentCustomer|Location|Fulfilment|Warehouse|PalletDelivery|RecurringBill $parent, $forElements = false): array
    {
        if ($parent instanceof FulfilmentCustomer) {
            $stats = $parent;
        } else {
            $stats = $parent->stats;
        }

        return [
            'in_process' => $stats->number_pallet_returns_state_in_process,
            'submitted' => $stats->number_pallet_returns_state_submitted,
            'confirmed' => $stats->number_pallet_returns_state_confirmed,
            'picking' => $stats->number_pallet_returns_state_picking,
            'picked' => $stats->number_pallet_returns_state_picked,
            'dispatched' => $stats->number_pallet_returns_state_dispatched,
            'cancel' => $stats->number_pallet_returns_state_cancel,
            // 'consolidated' => $stats->number_pallet_returns_state_consolidated,
        ];
    }

    public static function notifications(string $reference): array
    {
        return [
            'in_process' => [
                'title' => __('Pallet return :reference created', ['reference' => $reference]),
                'subtitle' => __('Pallet return has been created'),
            ],
            'submitted' => [
                'title' => __('Pallet return :reference submitted', ['reference' => $reference]),
                'subtitle' => __('Pallet return has been submitted'),
            ],
            'confirmed' => [
                'title' => __('Pallet return :reference confirmed', ['reference' => $reference]),
                'subtitle' => __('Pallet return has been confirmed'),
            ],
            'picking' => [
                'title' => __('Pallet return :reference picking', ['reference' => $reference]),
                'subtitle' => __('Pallet return is picking'),
            ],
            'picked' => [
                'title' => __('Pallet return :reference picked', ['reference' => $reference]),
                'subtitle' => __('Pallet return has been picked'),
            ],
            'dispatched' => [
                'title' => __('Pallet return :reference dispatched', ['reference' => $reference]),
                'subtitle' => __('Pallet return has been dispatched'),
            ],
            'cancel' => [
                'title' => __('Pallet return :reference cancelled', ['reference' => $reference]),
                'subtitle' => __('Pallet return has been cancelled'),
            ],
            // 'consolidated'     => [
            //     'title'    => __("Pallet return :reference consolidated", ['reference' => $reference]),
            //     'subtitle' => __('Pallet return has been consolidated')
            // ],
        ];
    }
}
