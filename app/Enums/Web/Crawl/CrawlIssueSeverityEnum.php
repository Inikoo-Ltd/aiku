<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\Web\Crawl;

use App\Enums\EnumHelperTrait;

enum CrawlIssueSeverityEnum: string
{
    use EnumHelperTrait;

    case ERROR   = 'error';
    case WARNING = 'warning';
    case NOTICE  = 'notice';

    public static function labels(): array
    {
        return [
            self::ERROR->value   => __('Error'),
            self::WARNING->value => __('Warning'),
            self::NOTICE->value  => __('Notice'),
        ];
    }

    public function rank(): int
    {
        return match ($this) {
            self::ERROR   => 1,
            self::WARNING => 2,
            self::NOTICE  => 3,
        };
    }
}
