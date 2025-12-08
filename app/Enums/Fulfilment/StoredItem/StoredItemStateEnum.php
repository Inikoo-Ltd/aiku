<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 03:02:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\StoredItem;

use App\Enums\EnumHelperTrait;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum StoredItemStateEnum: string
{
    use EnumHelperTrait;

    case SUBMITTED = 'submitted';
    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case DISCONTINUING = 'discontinuing';
    case DISCONTINUED = 'discontinued';

    public static function labels(): array
    {
        return [
            'submitted' => __('Submitted'),
            'in_process' => __('In Process'),
            'active' => __('Active'),
            'discontinuing' => __('Discontinuing'),
            'discontinued' => __('Discontinued'),
        ];
    }

    public function stateIcon(): array
    {
        return [
            'submitted' => [
                'tooltip' => __('Submitted'),
                'icon' => 'fal fa-share',
                'class' => 'text-indigo-500',  // Color for normal icon (Aiku)
                'color' => '#7CCE00',  // Color for box (Retina)
            ],
            'in_process' => [
                'tooltip' => __('In process'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-gray-300',  // Color for normal icon (Aiku)
                'color' => '#E5E7EB',  // Color for box (Retina)
            ],
            'active' => [
                'tooltip' => __('Active'),
                'icon' => 'fal fa-inventory',
                'class' => 'text-green-700',  // Color for normal icon (Aiku)
                'color' => '#008236',  // Color for box (Retina)
            ],
            'discontinuing' => [
                'tooltip' => __('Discontinuing'),
                'icon' => 'fal fa-sign-out-alt',
                'class' => 'text-red-500',  // Color for normal icon (Aiku)
                'color' => '#FB2C36',  // Color for box (Retina)
            ],
            'discontinued' => [
                'tooltip' => __('Discontinued'),
                'icon' => 'fal fa-ghost',
                'class' => 'text-gray-500',  // Color for normal icon (Aiku)
                'color' => '#6A7282',  // Color for box (Retina)
            ],
        ];
    }

    public static function count(
        Pallet|FulfilmentCustomer|Fulfilment|Organisation|Group|Warehouse $parent,
    ): array {
        if ($parent instanceof FulfilmentCustomer) {
            $stats = $parent;
        } elseif ($parent instanceof Organisation) {
            $stats = $parent->fulfilmentStats;
        } elseif ($parent instanceof Pallet) {
            $stats = $parent->stats;
        } else {
            $stats = $parent->stats;
        }

        return [
            'submitted' => $stats?->number_stored_items_in_submitted,
            'in_process' => $stats?->number_stored_items_in_process,
            'active' => $stats?->number_stored_items_received,
            'discontinuing' => $stats?->number_stored_items_booked_in,
            'discontinued' => $stats?->number_stored_items_settled,
        ];
    }
}
