<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Production\ProductionMaterialRequestItem;

use App\Enums\EnumHelperTrait;

/**
 * How the factory asks for a material, as it would say it to the warehouse. Only SKOs convert
 * to stock; the warehouse says how many SKOs it brought for the rest.
 */
enum ProductionMaterialRequestUnitEnum: string
{
    use EnumHelperTrait;

    case SKO = 'sko';
    case BOX = 'box';
    case PALLET = 'pallet';
    case BAG = 'bag';
    case DRUM = 'drum';
    case TRAY = 'tray';
    case ALL = 'all';

    public static function labels(): array
    {
        return [
            'sko'    => __('SKOs'),
            'box'    => __('Boxes'),
            'pallet' => __('Pallets'),
            'bag'    => __('Bags'),
            'drum'   => __('Drums'),
            'tray'   => __('Trays'),
            'all'    => __('All there is'),
        ];
    }

    public static function singularLabels(): array
    {
        return [
            'sko'    => __('SKO'),
            'box'    => __('Box'),
            'pallet' => __('Pallet'),
            'bag'    => __('Bag'),
            'drum'   => __('Drum'),
            'tray'   => __('Tray'),
            'all'    => __('All there is'),
        ];
    }
}
