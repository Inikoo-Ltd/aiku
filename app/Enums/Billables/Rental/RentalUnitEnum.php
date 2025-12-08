<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:28:32 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Billables\Rental;

use App\Enums\EnumHelperTrait;

enum RentalUnitEnum: string
{
    use EnumHelperTrait;

    case DAY = 'day';
    case WEEK = 'week';
    case MONTH = 'month';

    public static function labels(): array
    {
        return [
            'day' => __('day'),
            'week' => __('week'),
            'month' => __('month'),
        ];
    }

    public static function abbreviations(): array
    {
        return [
            'day' => 'd',
            'week' => 'w',
            'month' => 'm',
        ];
    }
}
