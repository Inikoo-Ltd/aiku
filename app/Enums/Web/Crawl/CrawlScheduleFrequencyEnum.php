<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\Web\Crawl;

use App\Enums\EnumHelperTrait;

enum CrawlScheduleFrequencyEnum: string
{
    use EnumHelperTrait;

    case DAILY   = 'daily';
    case WEEKLY  = 'weekly';
    case MONTHLY = 'monthly';

    public static function labels(): array
    {
        return [
            self::DAILY->value   => __('Daily'),
            self::WEEKLY->value  => __('Weekly'),
            self::MONTHLY->value => __('Monthly'),
        ];
    }
}
