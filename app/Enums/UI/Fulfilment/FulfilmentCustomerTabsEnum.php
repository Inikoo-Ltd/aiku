<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 15:16:47 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum FulfilmentCustomerTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE      = 'showcase';
    case AGREED_PRICES = 'agreed_prices';
    case HISTORY       = 'history';
    /*     case ATTACHMENTS   = 'attachments';
    case WEBHOOK       = 'webhook';
    case NOTE          = 'note'; */
    case ATTACHMENTS = 'attachments';
    case EMAIL = 'email';
    case BALANCE = 'balance';

    public function blueprint(): array
    {
        return match ($this) {
            FulfilmentCustomerTabsEnum::EMAIL => [
                'align' => 'right',
                'title' => __('Email'),
                'icon'  => 'fal fa-envelope',
                'type'  => 'icon'
            ],
            FulfilmentCustomerTabsEnum::ATTACHMENTS => [
                'align' => 'right',
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon'
            ],
            FulfilmentCustomerTabsEnum::HISTORY => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
            ],
            FulfilmentCustomerTabsEnum::BALANCE => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Balance'),
                'icon'  => 'fal fa-wallet',
            ],
            /* FulfilmentCustomerTabsEnum::WEBHOOK => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Webhook'),
                'icon'  => 'fal fa-clipboard-list-check',
            ], */
            FulfilmentCustomerTabsEnum::SHOWCASE => [
                'title' => __('Showcase'),
                'icon'  => 'fal fa-tachometer-alt',
            ],
            FulfilmentCustomerTabsEnum::AGREED_PRICES => [
                'title' => __('Agreed prices'),
                'icon'  => 'fal fa-usd-circle',
            ],

            //            FulfilmentCustomerTabsEnum::NOTE => [
            //                'title' => __('Note'),
            //                'icon'  => 'fal fa-sticky-note',
            //            ],
        };
    }
}
