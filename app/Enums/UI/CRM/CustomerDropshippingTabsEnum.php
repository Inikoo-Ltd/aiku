<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:16:37 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum CustomerDropshippingTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case HISTORY = 'history';
    case TIMELINE = 'timeline';
    case ATTACHMENTS = 'attachments';
    // case DISPATCHED_EMAILS   = 'dispatched_emails';
    case PAYMENTS = 'payments';
    case CREDIT_TRANSACTIONS = 'credit_transactions';
    case FAVOURITES = 'favourites';
    case REMINDERS = 'reminders';
    case DISPATCHED_EMAILS = 'dispatched_emails';

    public function blueprint(): array
    {
        return match ($this) {
            CustomerDropshippingTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon' => 'fal fa-tachometer-alt-fast',
            ],
            CustomerDropshippingTabsEnum::TIMELINE => [
                'title' => __('Timeline'),
                'icon' => 'fal fa-code-branch',
            ],
            CustomerDropshippingTabsEnum::ATTACHMENTS => [
                'align' => 'right',
                'title' => __('Attachments'),
                'icon' => 'fal fa-paperclip',
                'type' => 'icon',
            ],
            CustomerDropshippingTabsEnum::DISPATCHED_EMAILS => [
                'align' => 'right',
                'title' => __('Dispatched emails'),
                'icon' => 'fal fa-paper-plane',
                'type' => 'icon',
            ],
            CustomerDropshippingTabsEnum::PAYMENTS => [
                'align' => 'right',
                'title' => __('Payments'),
                'icon' => 'fal fa-money-bill',
                'type' => 'icon',
            ],
            CustomerDropshippingTabsEnum::CREDIT_TRANSACTIONS => [
                'align' => 'right',
                'title' => __('Credit transactions'),
                'icon' => 'fal fa-piggy-bank',
                'type' => 'icon',
            ],
            CustomerDropshippingTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
            CustomerDropshippingTabsEnum::REMINDERS => [
                'title' => __('Reminders'),
                'icon' => 'fal fa-bell',
                'align' => 'right',
                'type' => 'icon',
            ],
            CustomerDropshippingTabsEnum::FAVOURITES => [
                'title' => __('Favourites'),
                'icon' => 'fal fa-heart',
                'align' => 'right',
                'type' => 'icon',
            ],
        };
    }
}
