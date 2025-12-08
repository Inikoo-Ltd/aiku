<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Oct 2023 16:58:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Prospect;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ShowProspectTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case PROSPECTS = 'prospects';
    case HISTORY = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            ShowProspectTabsEnum::SHOWCASE => [
                'title' => __('Showcase'),
                'icon' => 'fas fa-info-circle',
            ],

            ShowProspectTabsEnum::PROSPECTS => [
                'title' => __('Prospects'),
                'icon' => 'fal fa-transporter',
            ],

            ShowProspectTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
        };
    }
}
