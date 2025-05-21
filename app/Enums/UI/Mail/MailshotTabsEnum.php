<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:45:45 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Mail;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum MailshotTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case RECIPIENTS = 'recipients';
    case DISPATCHED_EMAILS = 'dispatched_emails';

    public function blueprint(): array
    {
        return match ($this) {
            MailshotTabsEnum::SHOWCASE => [
                'title' => __('Showcase'),
                'icon'  => 'fal fa-tachometer-alt',
            ],
            MailshotTabsEnum::RECIPIENTS => [
                'title' => __('Recipients'),
                'icon'  => 'fal fa-users',
            ],
            MailshotTabsEnum::DISPATCHED_EMAILS => [
                'title' => __('Dispatched Emails'),
                'icon'  => 'fal fa-paper-plane',
            ],
        };
    }
}
