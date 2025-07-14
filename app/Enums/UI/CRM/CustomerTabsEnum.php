<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:16:37 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum CustomerTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE            = 'showcase';
    case HISTORY             = 'history';
    case TIMELINE            = 'timeline';
    case ATTACHMENTS         = 'attachments';
    // case DISPATCHED_EMAILS   = 'dispatched_emails';
    case CREDIT_TRANSACTIONS = 'credit_transactions';
    case FAVOURITES          = 'favourites';
    case REMINDERS           = 'reminders';
    case DISPATCHED_EMAILS   = 'dispatched_emails';


    public function blueprint(): array
    {
        return match ($this) {
            CustomerTabsEnum::SHOWCASE => [
                'title' => __('overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            CustomerTabsEnum::TIMELINE => [
                'title' => __('timeline'),
                'icon'  => 'fal fa-code-branch',
            ],

            CustomerTabsEnum::ATTACHMENTS => [
                'align' => 'right',
                'title' => __('attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon'
            ],
            CustomerTabsEnum::DISPATCHED_EMAILS => [
                'align' => 'right',
                'title' => __('dispatched emails'),
                'icon'  => 'fal fa-paper-plane',
                'type'  => 'icon',
            ],
            CustomerTabsEnum::CREDIT_TRANSACTIONS => [
                'align' => 'right',
                'title' => __('credit transactions'),
                'icon'  => 'fal fa-piggy-bank',
                'type'  => 'icon',
            ],
            CustomerTabsEnum::HISTORY => [
                'title' => __('history'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            CustomerTabsEnum::REMINDERS => [
                'title' => __('reminders'),
                'icon'  => 'fal fa-bell',
                'align' => 'right',
                'type'  => 'icon',
            ],
            CustomerTabsEnum::FAVOURITES => [
                'title' => __('favourites'),
                'icon'  => 'fal fa-heart',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
