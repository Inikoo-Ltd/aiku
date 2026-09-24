<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum WebUserTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE       = 'showcase';
    case LOGINS         = 'logins';
    case FAILED_LOGINS  = 'failed_logins';
    case HISTORY        = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            WebUserTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            WebUserTabsEnum::LOGINS => [
                'title' => __('Logins'),
                'icon'  => 'fal fa-sign-in-alt',
                'type'  => 'icon',
                'align' => 'right',
            ],
            WebUserTabsEnum::FAILED_LOGINS => [
                'title' => __('Failed logins'),
                'icon'  => 'fal fa-exclamation-triangle',
                'type'  => 'icon',
                'align' => 'right',
            ],
            WebUserTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
