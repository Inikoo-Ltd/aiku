<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\Pallet;

use App\Enums\EnumHelperTrait;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;

enum PalletStatusEnum: string
{
    use EnumHelperTrait;


    case IN_PROCESS   = 'in-process';
    case RECEIVING    = 'receiving';
    case NOT_RECEIVED = 'not-received';
    case STORING      = 'storing';
    case RETURNING    = 'returning';
    case RETURNED     = 'returned';
    case INCIDENT     = 'incident';

    public static function labels(FulfilmentCustomer|Fulfilment|Warehouse|Location|PalletDelivery|PalletReturn|null $parent = null): array
    {
        $labels = [
            'in-process'   => __('In process'),
            'receiving'    => __('Receiving'),
            'not-received' => __('Not received'),
            'storing'      => __('Storing'),
            'returning'    => __('Returning'),
            'returned'     => __('Returned'),
            'incident'     => __('Incidents'),

        ];


        if ($parent instanceof Fulfilment or $parent instanceof Warehouse) {
            unset($labels['in-process']);
            unset($labels['not-received']);
            unset($labels['incident']);
            unset($labels['returned']);
        } elseif ($parent instanceof FulfilmentCustomer) {
            unset($labels['in-process']);
        }

        return $labels;
    }

    public static function statusIcon(): array
    {
        return [
            'in-process'   => [
                'tooltip' => __('In process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-lime-500',  // Color for normal icon (Aiku)
                'color'   => 'lime',  // Color for box (Retina)
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5'
                ]
            ],
            'receiving'    => [
                'tooltip' => __('In process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-lime-500',  // Color for normal icon (Aiku)
                'color'   => 'lime',  // Color for box (Retina)
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5'
                ]
            ],
            'not-received' => [
                'tooltip' => __('not received'),
                'icon'    => 'fal fa-times',
                'class'   => 'text-red-500',
                'color'   => 'red',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],
            'storing'      => [
                'tooltip' => __('Storing'),
                'icon'    => 'fal fa-warehouse-alt',
                'class'   => 'text-purple-500',
                'color'   => 'purple',
                'app'     => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5'
                ]
            ],
            'incident'     => [
                'tooltip' => __('Incident'),
                'icon'    => 'fal fa-sad-cry',
                'class'   => 'text-red-600',
                'color'   => 'red',
                'app'     => [
                    'name' => 'sad-cry',
                    'type' => 'font-awesome-5'
                ]
            ],
            'returning'    => [
                'tooltip' => __('Returning'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-green-400',
                'color'   => 'green',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'returned'     => [
                'tooltip' => __('Returned'),
                'icon'    => 'fal fa-arrow-alt-from-left',
                'app'     => [
                    'name' => 'check',//todo need to change icon
                    'type' => 'font-awesome-5'
                ]
            ],
        ];
    }

    public static function count(
        Organisation|FulfilmentCustomer|Fulfilment|Warehouse|PalletDelivery|PalletReturn|Location $parent,
    ): array {
        if ($parent instanceof FulfilmentCustomer) {
            $stats = $parent;
        } elseif ($parent instanceof Organisation) {
            $stats = $parent->fulfilmentStats;
        } else {
            $stats = $parent->stats;
        }

        $counts = [
            'in-process'   => $stats->number_pallets_status_in_process,
            'receiving'    => $stats->number_pallets_status_receiving,
            'not-received' => $stats->number_pallets_status_not_received,
            'storing'      => $stats->number_pallets_status_storing,
            'returning'    => $stats->number_pallets_status_returning,
            'incident'     => $stats->number_pallets_status_incident,
            'returned'     => $stats->number_pallets_status_returned,
        ];
        if ($parent instanceof Fulfilment  or $parent instanceof Warehouse) {
            unset($counts['in-process']);
            unset($counts['not-received']);
            unset($counts['incident']);
            unset($counts['returned']);
        } elseif ($parent instanceof FulfilmentCustomer) {
            unset($counts['in-process']);
        }


        return $counts;
    }

}
