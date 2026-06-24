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
    case TIMELINE            = 'timeline';

    case HISTORY             = 'history';
    case ATTACHMENTS         = 'attachments';
    case PAYMENTS            = 'payments';
    case CREDIT_TRANSACTIONS = 'credit_transactions';
    case FAVOURITES          = 'favourites';
    case REMINDERS           = 'reminders';
    case DISPATCHED_EMAILS   = 'dispatched_emails';
    case OFFERS   = 'offers';


    public function blueprint(): array
    {
        return match ($this) {
            CustomerTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            CustomerTabsEnum::TIMELINE => [
                'title' => __('Timeline'),
                'icon'  => 'fal fa-code-branch',
            ],

            CustomerTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            CustomerTabsEnum::ATTACHMENTS => [
                'align' => 'right',
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon'
            ],
            CustomerTabsEnum::CREDIT_TRANSACTIONS => [
                'align' => 'right',
                'title' => __('Credit transactions'),
                'icon'  => 'fal fa-piggy-bank',
                'type'  => 'icon',
            ],
            CustomerTabsEnum::PAYMENTS => [
                'align' => 'right',
                'title' => __('Payments'),
                'icon'  => 'fal fa-money-bill',
                'type'  => 'icon',
            ],
            CustomerTabsEnum::FAVOURITES => [
                'title' => __('Favourites'),
                'icon'  => 'fal fa-heart',
                'type'  => 'icon',
                'align' => 'right',
            ],
            CustomerTabsEnum::REMINDERS => [
                'title' => __('Reminders'),
                'icon'  => 'fal fa-bell',
                'align' => 'right',
                'type'  => 'icon',
            ],
            CustomerTabsEnum::DISPATCHED_EMAILS => [
                'align' => 'right',
                'title' => __('Dispatched emails'),
                'icon'  => 'fal fa-paper-plane',
                'type'  => 'icon',
            ],
            CustomerTabsEnum::OFFERS => [
                'align' => 'right',
                'title' => __('Offers'),
                'icon'  => 'fal fa-badge-percent',
                'type'  => 'icon',
            ],

        };
    }
}
