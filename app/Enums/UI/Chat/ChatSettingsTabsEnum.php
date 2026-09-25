<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Enums\UI\Chat;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ChatSettingsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case AGENTS = 'agents';
    case WHATSAPP_TEMPLATES = 'whatsapp_templates';
    case OUT_OF_HOURS = 'out_of_hours';
    case POLICIES = 'policies';

    public function blueprint(): array
    {
        return match ($this) {
            ChatSettingsTabsEnum::AGENTS => [
                'title' => __('Agents'),
                'icon'  => 'fal fa-headset',
            ],
            ChatSettingsTabsEnum::WHATSAPP_TEMPLATES => [
                'title' => __('WhatsApp templates'),
                'icon'  => 'fab fa-whatsapp',
            ],
            ChatSettingsTabsEnum::OUT_OF_HOURS => [
                'title' => __('Out of hours email'),
                'icon'  => 'fal fa-moon',
            ],
            ChatSettingsTabsEnum::POLICIES => [
                'title' => __('Facts for AI replies'),
                'icon'  => 'fal fa-book',
            ],
        };
    }
}
