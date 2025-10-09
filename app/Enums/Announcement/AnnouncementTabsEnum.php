<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Sep 2023 11:54:26 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Announcement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum AnnouncementTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case SNAPSHOTS = 'snapshots';

    public function blueprint(): array
    {
        return match ($this) {
            AnnouncementTabsEnum::SHOWCASE => [
                'align' => 'right',
                'title' => __('Showcase'),
                'icon' => 'fal fa-money-bill',
                'type' => 'icon',
            ],
            AnnouncementTabsEnum::SNAPSHOTS => [
                'align' => 'right',
                'title' => __('Snapshots'),
                'icon' => 'fal fa-money-bill',
                'type' => 'icon',
            ],
        };
    }

    public static function labels(): array
    {
        return [
            'showcase' => __('Showcase'),
            'snapshots'      => __('Snapshots')
        ];
    }
}
