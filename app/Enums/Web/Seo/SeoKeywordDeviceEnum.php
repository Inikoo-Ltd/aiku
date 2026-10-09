<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\Web\Seo;

use App\Enums\EnumHelperTrait;

enum SeoKeywordDeviceEnum: string
{
    use EnumHelperTrait;

    case MOBILE  = 'mobile';
    case DESKTOP = 'desktop';

    public static function labels(): array
    {
        return [
            self::MOBILE->value  => __('Mobile'),
            self::DESKTOP->value => __('Desktop'),
        ];
    }
}
