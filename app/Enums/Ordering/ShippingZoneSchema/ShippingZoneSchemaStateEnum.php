<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Sept 2024 12:51:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\ShippingZoneSchema;

use App\Enums\EnumHelperTrait;

enum ShippingZoneSchemaStateEnum: string
{
    use EnumHelperTrait;
    case IN_PROCESS    = 'in_process';
    case LIVE       = 'live';
    case DECOMMISSIONED = 'decommissioned';

    public static function labels($forElements = false): array
    {
        return [
            'in_process'   => __('In Process'),
            'live'         => __('Live'),
            'decommissioned' => __('Decommissioned'),
        ];
    }

    public static function stateIcon(): array
    {
        // Icon is imported in resources/js/Composables/Icon/PalletReturnStateEnum.ts
        return [
            'in_process' => [
                'tooltip' => __('In process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-lime-500',  // Color for normal icon (Aiku)
                'color'   => '#7CCE00',  // Color for box (Retina)
            ],
            'live' => [
                'tooltip' => __('Live'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-green-500',
                'color'   => '#00B140',
            ],
            'decommissioned' => [
                'tooltip' => __('Decommissioned'),
                'icon'    => 'fal fa-ban',
                'class'   => 'text-red-500',
                'color'   => '#FB2C36',
            ],
        ];
    }
}
