<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:13:49 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ProspectTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;


    case SHOWCASE            = 'showcase';
    case HISTORY             = 'history';
    case DISPATCHED_EMAILS   = 'dispatched_emails';



    public function blueprint(): array
    {
        return match ($this) {
            ProspectTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            ProspectTabsEnum::DISPATCHED_EMAILS => [
                'title' => __('Dispatched emails'),
                'icon'  => 'fal fa-paper-plane',
            ],
            ProspectTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
