<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 14:31:41 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\SysAdmin;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum UsersTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ACTIVE_USERS    = 'active_users';
    case SUSPENDED_USERS = 'suspended_users';
    case USERS_REQUESTS  = 'users_requests';
    case USERS_HISTORIES = 'users_histories';
    case USERS           = 'users';

    public function blueprint(): array
    {
        return match ($this) {
            UsersTabsEnum::ACTIVE_USERS => [
                'title' => __('Active users'),
                'icon'  => 'fal fa-user',
            ],
            UsersTabsEnum::SUSPENDED_USERS => [
                'title' => __('Suspended users'),
                'icon'  => 'fal fa-user-slash',
            ],
            UsersTabsEnum::USERS => [
                'title' => __('All users'),
                'icon'  => 'fal fa-blender',
                'type'  => 'icon',
                'align' => 'right'
            ],
            UsersTabsEnum::USERS_REQUESTS => [
                'title' => __('users requests'),
                'icon'  => 'fal fa-road',
            ],
            UsersTabsEnum::USERS_HISTORIES => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right'
            ],
        };
    }
}
