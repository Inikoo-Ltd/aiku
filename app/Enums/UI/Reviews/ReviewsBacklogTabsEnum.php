<?php

namespace App\Enums\UI\Reviews;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ReviewsBacklogTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case WAITING = 'waiting';
    case UNANSWERED = 'unanswered';
    case PUBLISHED = 'published';
    case PUBLISHED_LAST_24H = 'published_last_24h';
    case REJECTED = 'rejected';
}
