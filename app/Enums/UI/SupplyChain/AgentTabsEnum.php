<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 04 May 2024 13:06:25 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum AgentTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE            = 'showcase';
    case DETAILS             = 'details';
    case AGREED_TERMS = 'agreed_terms';
    case NOTES = 'notes';
    case SYSTEM_USERS        = 'system_users';
    case HISTORY             = 'history';
    case ATTACHMENTS         = 'attachments';





    public function blueprint(): array
    {
        return match ($this) {

            AgentTabsEnum::ATTACHMENTS => [
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon',
                'align' => 'right',
            ],
            AgentTabsEnum::HISTORY     => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            AgentTabsEnum::AGREED_TERMS => [
                'title' => __('Agreed terms'),
                'icon'  => 'fal fa-handshake',
            ],
            AgentTabsEnum::SYSTEM_USERS => [
                'title' => __('System users'),
                'icon'  => 'fal fa-terminal',
            ],
            AgentTabsEnum::NOTES => [
                'title' => __('Notes'),
                'icon'  => 'fal fa-sticky-note',
            ],
            AgentTabsEnum::DETAILS => [
                'title' => __('Details'),
                'icon'  => 'fal fa-address-book',
            ],
            AgentTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt',
            ],

        };
    }
}
