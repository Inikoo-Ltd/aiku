<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 29 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\DevOps;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum AikuPublicAnalyticsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case OVERVIEW = 'overview';
    case ARTICLES = 'articles';
    case HASHTAGS = 'hashtags';

    public function blueprint(): array
    {
        return match ($this) {
            AikuPublicAnalyticsTabsEnum::OVERVIEW => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            AikuPublicAnalyticsTabsEnum::ARTICLES => [
                'title' => __('Articles'),
                'icon'  => 'fal fa-newspaper',
            ],
            AikuPublicAnalyticsTabsEnum::HASHTAGS => [
                'title' => __('Hashtags'),
                'icon'  => 'fal fa-hashtag',
            ],
        };
    }
}
