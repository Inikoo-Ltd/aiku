<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\UI\Web;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum SeoKeywordsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case TRACKED_KEYWORDS = 'tracked_keywords';
    case COMPETITORS      = 'competitors';

    public function blueprint(): array
    {
        return match ($this) {
            SeoKeywordsTabsEnum::TRACKED_KEYWORDS => [
                'title'   => __('Tracked keywords'),
                'icon'    => 'fal fa-key',
                'tooltip' => __('Keywords whose Google position will be checked for this shop, with the country, language, device and how often to check.'),
            ],
            SeoKeywordsTabsEnum::COMPETITORS => [
                'title'   => __('Competitors'),
                'icon'    => 'fal fa-trophy',
                'tooltip' => __('Domains to compare against: their positions on the tracked keywords, and later their backlinks.'),
            ],
        };
    }
}
