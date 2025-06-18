<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 14:31:41 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\SysAdmin;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum UserTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE     = 'showcase';
    case HISTORY      = 'history';
    case API_TOKENS   = 'api_tokens';
    // case REQUEST_LOGS = 'request_logs';

    // case PERMISSIONS = 'permissions';
    // case ROLES       = 'roles';

    public function blueprint(): array
    {
        return match ($this) {
            // UserTabsEnum::PERMISSIONS => [
            //     'title' => __('permissions'),
            //     'icon'  => 'fal fa-shield-check',
            //     'type'  => 'icon',
            //     'align' => 'right',
            // ],
            // UserTabsEnum::ROLES => [
            //     'title' => __('roles'),
            //     'icon'  => 'fal fa-user-tag',
            //     'type'  => 'icon',
            //     'align' => 'right',
            // ],
            UserTabsEnum::HISTORY => [
                'title' => __('history'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            UserTabsEnum::SHOWCASE => [
                'title' => __('user'),
                'icon'  => 'fas fa-info-circle',
            ],
            UserTabsEnum::API_TOKENS => [
                'title' => __('api tokens'),
                'icon'  => 'fal fa-key',
                'type'  => 'icon',
            ],
            // UserTabsEnum::REQUEST_LOGS => [
            //     'title' => __('Visit logs'),
            //     'icon'  => 'fas fa-road',
            // ],
        };
    }
}
