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

    /**
     * The devices keywords are tracked, checked and shown on: desktop only since 5 Nov 2026. Mobile
     * rows added before stay in the database with their history, but are no longer checked or shown.
     *
     * @return array<int, self>
     */
    public static function tracked(): array
    {
        return [self::DESKTOP];
    }

    public static function labels(): array
    {
        return [
            self::MOBILE->value  => __('Mobile'),
            self::DESKTOP->value => __('Desktop'),
        ];
    }
}
