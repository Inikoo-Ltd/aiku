<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Sept 2024 16:41:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Inventory\OrgStockMovement;

use App\Enums\EnumHelperTrait;

enum OrgStockMovementClassEnum: string
{
    use EnumHelperTrait;

    case MOVEMENT = 'movement';
    case INFO = 'info';
    case HELPER = 'helper';
    case GARBAGE = 'garbage';


    public function icon(): array
    {
        return match ($this) {
            self::MOVEMENT  => [
                'tooltip' => __('Movement'),
                'icon'    => 'fal fa-person-carry',
                'class'   => 'text-purple-500',
            ],
            self::INFO      => [
                'tooltip' => __('Info'),
                'icon'    => 'fal fa-info-circle',
                'class'   => 'text-blue-500',
            ],
            self::HELPER    => [
                'tooltip' => __('Helper'),
                'icon'    => 'fal fa-hands-helping',
                'class'   => 'text-yellow-500',
            ],
            self::GARBAGE   => [
                'tooltip' => __('Garbage'),
                'icon'    => 'fal fa-dumpster',
                'class'   => 'text-grey-700',
            ],
            default         => [
                'tooltip' => __('Unknown'),
                'icon'    => 'fal fa-question-circle',
                'class'   => 'text-orange-500',
            ],
        };
    }
}
