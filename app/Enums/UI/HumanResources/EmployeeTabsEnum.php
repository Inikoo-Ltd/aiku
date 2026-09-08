<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:14:46 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\HumanResources;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum EmployeeTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE                       = 'showcase';
    case HISTORY                        = 'history';
    case ATTACHMENTS                    = 'attachments';
    case REQUESTS                       = 'requests';
    case SEARCHES                       = 'searches';
    case CHATS                          = 'chats';
    case AI_QUERIES                     = 'ai_queries';

    /**
     * @return array<int, EmployeeTabsEnum>
     */
    public static function userActivityTabs(): array
    {
        return [self::REQUESTS, self::SEARCHES, self::CHATS, self::AI_QUERIES];
    }


    public function blueprint(): array
    {
        return match ($this) {
            EmployeeTabsEnum::ATTACHMENTS => [
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon',
                'align' => 'right',
            ],

            EmployeeTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            EmployeeTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            EmployeeTabsEnum::REQUESTS => [
                'title' => __('Requests'),
                'icon'  => 'fal fa-globe',
            ],
            EmployeeTabsEnum::SEARCHES => [
                'title' => __('Searches'),
                'icon'  => 'fal fa-search',
            ],
            EmployeeTabsEnum::CHATS => [
                'title' => __('Chats'),
                'icon'  => 'fal fa-comments',
            ],
            EmployeeTabsEnum::AI_QUERIES => [
                'title' => __('AI queries'),
                'icon'  => 'fal fa-robot',
            ],
        };
    }
}
