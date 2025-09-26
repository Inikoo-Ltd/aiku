<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:13:49 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ProspectsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DASHBOARD = 'dashboard';
    case PROSPECTS = 'prospects';
    case CONTACTED = 'contacted';
    case FAILED    = 'failed';
    case SUCCESS   = 'success';


    case HISTORY   = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            ProspectsTabsEnum::DASHBOARD => [
                'title' => __('Dashboard'),
                'icon'  => 'fal fa-tachometer-alt',
            ],

            ProspectsTabsEnum::PROSPECTS => [
                'title' => __('Prospects'),
                'icon'  => 'fal fa-transporter',
            ],

            ProspectsTabsEnum::CONTACTED => [
                'title' => __('Contacted prospects'),
                'icon'  => 'fal fa-transporter',
            ],

            ProspectsTabsEnum::FAILED => [
                'title' => __('Fail prospects'),
                'icon'  => 'fal fa-transporter',
            ],

            ProspectsTabsEnum::SUCCESS => [
                'title' => __('Success prospects'),
                'icon'  => 'fal fa-transporter',
            ],
            ProspectsTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right'
            ]
        };
    }
}
