<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Jun 2023 23:19:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Period;

use App\Enums\EnumHelperTrait;

enum PeriodEnum: string
{
    use EnumHelperTrait;

    case Day = 'day';
    case WEEK = 'week';
    case MONTH = 'month';
    case QUARTER = 'quarter';
    case YEAR = 'year';

    public static function labels(): array
    {
        return [
            'day' => __('Day'),
            'week' => __('Week'),
            'month' => __('Month'),
            'quarter' => __('Quarter'),
            'year' => __('Year'),
        ];
    }

    public static function date(): array
    {
        $now = now();
        $quarter = ceil($now->format('n') / 3);

        return [
            'day' => $now->format('Ymd'),
            'week' => $now->format('oW'),  // '202422', 'o' is ISO-8601 year, 'W' is ISO-8601 week number of year
            'month' => $now->format('Ym'),
            'quarter' => $now->format('Y').'Q'.$quarter,  // 2024Q2
            'year' => $now->format('Y'),
        ];
    }
}
