<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\Web\Crawl;

use App\Enums\EnumHelperTrait;

enum CrawlLinkKindEnum: string
{
    use EnumHelperTrait;

    case HYPERLINK = 'hyperlink';
    case IMAGE     = 'image';
    case CANONICAL = 'canonical';
    case HREFLANG  = 'hreflang';
    case NEXT      = 'next';
    case PREV      = 'prev';

    public static function labels(): array
    {
        return [
            self::HYPERLINK->value => __('Hyperlink'),
            self::IMAGE->value     => __('Image'),
            self::CANONICAL->value => __('Canonical'),
            self::HREFLANG->value  => __('Hreflang'),
            self::NEXT->value      => __('Pagination next'),
            self::PREV->value      => __('Pagination previous'),
        ];
    }
}
