<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\UI\Web;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum SeoCompetitorsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DOMAINS     = 'domains';
    case COMPARISON  = 'comparison';
    case KEYWORD_GAP = 'keyword_gap';

    public function blueprint(): array
    {
        return match ($this) {
            SeoCompetitorsTabsEnum::DOMAINS => [
                'title'   => __('Competitors'),
                'icon'    => 'fal fa-users',
                'tooltip' => __('The competitor domains of this shop, used by Rankings, Backlinks and the tools here, and the ones Google results suggest.'),
            ],
            SeoCompetitorsTabsEnum::COMPARISON => [
                'title'   => __('Domain comparison'),
                'icon'    => 'fal fa-exchange',
                'tooltip' => __('Our domain next to up to four others: authority, links, organic keywords and traffic.'),
            ],
            SeoCompetitorsTabsEnum::KEYWORD_GAP => [
                'title'   => __('Keyword gap'),
                'icon'    => 'fal fa-key',
                'tooltip' => __('Keywords the competitors rank for and we do not, or rank better for.'),
            ],
        };
    }
}
