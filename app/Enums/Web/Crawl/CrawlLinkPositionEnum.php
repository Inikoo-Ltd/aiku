<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\Web\Crawl;

use App\Enums\EnumHelperTrait;

enum CrawlLinkPositionEnum: string
{
    use EnumHelperTrait;

    case HEADER  = 'header';
    case NAV     = 'nav';
    case ASIDE   = 'aside';
    case FOOTER  = 'footer';
    case CONTENT = 'content';
    case HEAD    = 'head';

    public static function labels(): array
    {
        return [
            self::HEADER->value  => __('Header'),
            self::NAV->value     => __('Navigation'),
            self::ASIDE->value   => __('Sidebar'),
            self::FOOTER->value  => __('Footer'),
            self::CONTENT->value => __('Content'),
            self::HEAD->value    => __('Head'),
        ];
    }
}
