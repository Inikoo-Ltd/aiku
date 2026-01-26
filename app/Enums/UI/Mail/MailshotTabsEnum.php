<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:45:45 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Mail;

use App\Enums\EnumHelperTrait;
use App\Models\Comms\Mailshot;

enum MailshotTabsEnum: string
{
    use EnumHelperTrait;

    case SHOWCASE = 'showcase';
    case RECIPIENTS = 'recipients';
    case DISPATCHED_EMAILS = 'dispatched_emails';

    public function blueprint(Mailshot $mailshot): array
    {
        return match ($this) {
            MailshotTabsEnum::SHOWCASE => [
                'title' => __('Showcase'),
                'icon'  => 'fal fa-tachometer-alt',
            ],
            MailshotTabsEnum::RECIPIENTS => [
                'title' => __('Recipients') . " ({$mailshot->recipients->count()})",
                'icon'  => 'fal fa-users',
            ],
            MailshotTabsEnum::DISPATCHED_EMAILS => [
                'title' => __('Dispatched Emails') . " ({$mailshot->stats->number_dispatched_emails})",
                'icon'  => 'fal fa-paper-plane',
            ],
        };
    }

    public static function navigation(Mailshot $mailshot): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) use ($mailshot) {
            return  [$case->value => $case->blueprint($mailshot)];
        })->all();
    }
}
