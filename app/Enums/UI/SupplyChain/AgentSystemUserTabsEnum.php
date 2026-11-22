<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum AgentSystemUserTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE   = 'showcase';
    case LOGINS     = 'logins';
    case REQUESTS   = 'requests';
    case SEARCHES   = 'searches';
    case CHATS      = 'chats';
    case AI_QUERIES = 'ai_queries';
    case CHANGES    = 'changes';
    case HISTORY    = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            AgentSystemUserTabsEnum::SHOWCASE => [
                'title' => __('User'),
                'icon'  => 'fas fa-info-circle',
            ],
            AgentSystemUserTabsEnum::LOGINS => [
                'title' => __('Logins'),
                'icon'  => 'fal fa-sign-in-alt',
            ],
            AgentSystemUserTabsEnum::REQUESTS => [
                'title' => __('Pages visited'),
                'icon'  => 'fal fa-globe',
            ],
            AgentSystemUserTabsEnum::SEARCHES => [
                'title' => __('Searches'),
                'icon'  => 'fal fa-search',
            ],
            AgentSystemUserTabsEnum::CHATS => [
                'title' => __('Chats'),
                'icon'  => 'fal fa-comments',
            ],
            AgentSystemUserTabsEnum::AI_QUERIES => [
                'title' => __('AI queries'),
                'icon'  => 'fal fa-robot',
            ],
            AgentSystemUserTabsEnum::CHANGES => [
                'title' => __('Changes made'),
                'icon'  => 'fal fa-pencil',
            ],
            AgentSystemUserTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
