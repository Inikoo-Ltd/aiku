<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 12:22:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\TimeSeries;

use App\Enums\EnumHelperTrait;

enum TimeSeriesFrequencyEnum: string
{
    use EnumHelperTrait;

    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';

    public static function labels(): array
    {
        return [
            'daily'     => __('Daily'),
            'weekly'    => __('Weekly'),
            'monthly'   => __('Monthly'),
            'quarterly' => __('Quarterly'),
            'yearly'    => __('Yearly'),
        ];
    }

    public function singleLetter(): string
    {
        return match ($this) {
            self::DAILY => 'D',
            self::WEEKLY => 'W',
            self::MONTHLY => 'M',
            self::QUARTERLY => 'Q',
            self::YEARLY => 'Y',
        };
    }

}
