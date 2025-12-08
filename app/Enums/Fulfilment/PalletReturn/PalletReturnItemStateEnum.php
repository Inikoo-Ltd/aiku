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
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;

enum PalletReturnItemStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case SUBMITTED = 'submitted';
    case CONFIRMED = 'confirmed';
    case PICKING = 'picking';
    case PICKED = 'picked';
    case NOT_PICKED = 'not_picked';
    case DISPATCHED = 'dispatched';
    case CANCEL = 'cancel';

    public static function labels(): array
    {
        return [
            'in_process' => __('In Process'),
            'submitted' => __('Submitted'),
            'confirmed' => __('Confirmed'),
            'picking' => __('Picking'),
            'picked' => __('Picked'),
            'not_picked' => __('Not Picked'),
            'dispatched' => __('Dispatched'),
            'cancel' => __('Cancel'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [
                'tooltip' => __('In process'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-lime-500',  // Color for normal icon (Aiku)
                'color' => 'lime',  // Color for box (Retina)
            ],
            'submitted' => [
                'tooltip' => __('Submitted'),
                'icon' => 'fal fa-share',
                'class' => 'text-indigo-400',
                'color' => 'indigo',
            ],
            'confirmed' => [
                'tooltip' => __('Confirmed'),
                'icon' => 'fal fa-spell-check',
                'class' => 'text-emerald-500',
                'color' => 'emerald',
            ],
            'picking' => [
                'tooltip' => __('Picking'),
                'icon' => 'fal fa-truck',
                'class' => 'text-orange-500',
                'color' => 'orange',
            ],
            'picked' => [
                'tooltip' => __('Picked'),
                'icon' => 'fal fa-check',
                'class' => 'text-slate-500',
                'color' => 'slate',
            ],
            'not_picked' => [
                'tooltip' => __('Not Picked'),
                'icon' => 'fal fa-times',
                'class' => 'text-red-500',
                'color' => 'red',
            ],
            'dispatched' => [
                'tooltip' => __('Dispatched'),
                'icon' => 'fal fa-check-double',
                'class' => 'text-purple-500',
                'color' => 'purple',
            ],
            'cancel' => [
                'tooltip' => __('Cancel'),
                'icon' => 'fal fa-times',
                'class' => 'text-red-500',
                'color' => 'red',
            ],
        ];
    }

    public static function count(Organisation|FulfilmentCustomer|Location|Fulfilment|Warehouse|PalletDelivery $parent): array
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
            'not_picked' => $stats->number_pallet_returns_state_not_picked,
            'dispatched' => $stats->number_pallet_returns_state_dispatched,
            'cancel' => $stats->number_pallet_returns_state_cancel,
        ];
    }
}
