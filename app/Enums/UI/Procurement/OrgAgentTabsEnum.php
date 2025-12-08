<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 02 May 2024 19:19:04 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Procurement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrgAgentTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case SYSTEM_USERS = 'system_users';
    case HISTORY = 'history';
    case DATA = 'data';
    case IMAGES = 'images';

    public function blueprint(): array
    {
        return match ($this) {
            OrgAgentTabsEnum::DATA => [
                'title' => __('Data'),
                'icon' => 'fal fa-database',
                'type' => 'icon',
                'align' => 'right',
            ],
            OrgAgentTabsEnum::IMAGES => [
                'title' => __('Images'),
                'icon' => 'fal fa-camera-retro',
                'type' => 'icon',
                'align' => 'right',
            ],
            OrgAgentTabsEnum::SYSTEM_USERS => [
                'title' => __('System user'),
                'icon' => 'fal fa-terminal',
            ],
            OrgAgentTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
            OrgAgentTabsEnum::SHOWCASE => [
                'title' => __('Agent'),
                'icon' => 'fas fa-info-circle',
            ],
        };
    }
}
