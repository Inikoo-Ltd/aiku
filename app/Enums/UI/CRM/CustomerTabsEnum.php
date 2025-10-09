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
    case CREDIT_TRANSACTIONS = 'credit_transactions';
    case PAYMENTS            = 'payments';
    case FAVOURITES          = 'favourites';
    case REMINDERS           = 'reminders';
    case DISPATCHED_EMAILS   = 'dispatched_emails';


    public function blueprint(): array
    {
        return match ($this) {
            CustomerTabsEnum::PAYMENTS => [
                'align' => 'right',
                'title' => __('Payments'),
                'icon'  => 'fal fa-money-bill',
                'type'  => 'icon',
            ],
            CustomerTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            CustomerTabsEnum::TIMELINE => [
                'title' => __('Timeline'),
                'icon'  => 'fal fa-code-branch',
            ],
            CustomerTabsEnum::ATTACHMENTS => [
                'align' => 'right',
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon'
            ],
            CustomerTabsEnum::DISPATCHED_EMAILS => [
                'align' => 'right',
                'title' => __('Dispatched emails'),
                'icon'  => 'fal fa-paper-plane',
                'type'  => 'icon',
            ],
            CustomerTabsEnum::CREDIT_TRANSACTIONS => [
                'align' => 'right',
                'title' => __('Credit transactions'),
                'icon'  => 'fal fa-piggy-bank',
                'type'  => 'icon',
            ],
            CustomerTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            CustomerTabsEnum::REMINDERS => [
                'title' => __('Reminders'),
                'icon'  => 'fal fa-bell',
                'align' => 'right',
                'type'  => 'icon',
            ],
            CustomerTabsEnum::FAVOURITES => [
                'title' => __('Favourites'),
                'icon'  => 'fal fa-heart',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
