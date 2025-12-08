<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:17:52 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum CustomerNotificationTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SETTINGS = 'setting';
    case NOTIFICATIONS_TO_BE_SEND_NEXT_SHOT = 'notifications_to_be_send_next_shot';
    case WORKSHOP = 'workshop';

    case MAILSHOTS = 'mailshots';
    case SENT_EMAILS = 'sent_emails';

    case CHANGELOG = 'changelog';

    public function blueprint(): array
    {
        return match ($this) {
            CustomerNotificationTabsEnum::SETTINGS => [
                'title' => __('Setting'),
                'icon' => 'fal fa-sliders-h',
            ],
            CustomerNotificationTabsEnum::NOTIFICATIONS_TO_BE_SEND_NEXT_SHOT => [
                'title' => __('Notifications to be send next shot'),
                'icon' => 'fal fa-user-clock',
            ],
            CustomerNotificationTabsEnum::WORKSHOP => [
                'title' => __('Workshop'),
                'icon' => 'fal fa-wrench',
            ],
            CustomerNotificationTabsEnum::MAILSHOTS => [
                'title' => __('Mailshots'),
                'icon' => 'fal fa-container-storage',
                'type' => 'icon-only',
            ],
            CustomerNotificationTabsEnum::SENT_EMAILS => [
                'title' => __('Sent emails'),
                'icon' => 'fal fa-paper-plane',
                'type' => 'icon-only',
            ],
            CustomerNotificationTabsEnum::CHANGELOG => [
                'title' => __('Changelog'),
                'icon' => 'fal fa-road',
                'type' => 'icon-only',
            ],
        };
    }
}
