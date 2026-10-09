<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\UI\Web;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum SeoBacklinksTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case OVERVIEW          = 'overview';
    case REFERRING_DOMAINS = 'referring_domains';
    case BACKLINKS         = 'backlinks';
    case GAP               = 'gap';

    public function blueprint(): array
    {
        return match ($this) {
            SeoBacklinksTabsEnum::OVERVIEW => [
                'title'   => __('Overview'),
                'icon'    => 'fal fa-tachometer-alt',
                'tooltip' => __('Rank, referring domains and backlinks of our website and its competitors, with their trend.'),
            ],
            SeoBacklinksTabsEnum::REFERRING_DOMAINS => [
                'title'   => __('Referring domains'),
                'icon'    => 'fal fa-globe',
                'tooltip' => __('The domains linking to our website, new and lost since the previous weekly run.'),
            ],
            SeoBacklinksTabsEnum::BACKLINKS => [
                'title'   => __('Backlinks'),
                'icon'    => 'fal fa-external-link-alt',
                'tooltip' => __('The links to our website one by one: new, lost and broken.'),
            ],
            SeoBacklinksTabsEnum::GAP => [
                'title'   => __('Backlink gap'),
                'icon'    => 'fal fa-exchange',
                'tooltip' => __('Domains linking to competitors but not to us.'),
            ],
        };
    }
}
