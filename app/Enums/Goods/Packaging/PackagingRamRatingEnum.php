<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\Packaging;

use App\Enums\EnumHelperTrait;

/**
 * UK pEPR recyclability assessment (RAM) of a packaging component.
 */
enum PackagingRamRatingEnum: string
{
    use EnumHelperTrait;

    case GREEN   = 'green';
    case AMBER   = 'amber';
    case RED     = 'red';
    case UNKNOWN = 'unknown';

    public static function labels(): array
    {
        return [
            'green'   => __('Green'),
            'amber'   => __('Amber'),
            'red'     => __('Red'),
            'unknown' => __('Not assessed'),
        ];
    }
}
