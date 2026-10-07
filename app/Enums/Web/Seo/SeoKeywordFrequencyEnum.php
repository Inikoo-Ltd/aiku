<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\Web\Seo;

use App\Enums\EnumHelperTrait;

enum SeoKeywordFrequencyEnum: string
{
    use EnumHelperTrait;

    case WEEKLY = 'weekly';
    case DAILY  = 'daily';

    public static function labels(): array
    {
        return [
            self::WEEKLY->value => __('Weekly'),
            self::DAILY->value  => __('Daily'),
        ];
    }
}
