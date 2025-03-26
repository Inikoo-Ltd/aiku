<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:45:45 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Mail;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OutboxTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case MAILSHOTS = 'mailshots';
    case DISPATCHED_EMAILS = 'dispatched_emails';

    public function blueprint(): array
    {
        return match ($this) {
            OutboxTabsEnum::SHOWCASE => [
                'title' => __('overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            OutboxTabsEnum::MAILSHOTS => [
                'title' => __('mailshots'),
                'icon'  => 'fal fa-mail-bulk',
            ],
            OutboxTabsEnum::DISPATCHED_EMAILS => [
                'title' => __('dispatched emails'),
                'icon'  => 'far fa-paper-plane',
            ],
        };
    }
}
