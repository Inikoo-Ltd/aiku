<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 21-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Enums\UI\Mail;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum DispatchedEmailTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case EMAIL_TRACKING_EVENTS = 'email_tracking_events';

    public function blueprint(): array
    {
        return match ($this) {
            DispatchedEmailTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon' => 'fal fa-tachometer-alt-fast',
            ],
            DispatchedEmailTabsEnum::EMAIL_TRACKING_EVENTS => [
                'title' => __('Tracking'),
                'icon' => 'fal fa-stopwatch',
            ],
        };
    }
}
