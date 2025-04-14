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
                'title' => __('email'),
                'icon'  => 'fal fa-envelope',
                'type'  => 'icon'
            ],
            FulfilmentCustomerTabsEnum::ATTACHMENTS => [
                'align' => 'right',
                'title' => __('attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon'
            ],
            FulfilmentCustomerTabsEnum::HISTORY => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('history'),
                'icon'  => 'fal fa-clock',
            ],
            FulfilmentCustomerTabsEnum::BALANCE => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('balance'),
                'icon'  => 'fal fa-wallet',
            ],
            /* FulfilmentCustomerTabsEnum::WEBHOOK => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('webhook'),
                'icon'  => 'fal fa-clipboard-list-check',
            ], */
            FulfilmentCustomerTabsEnum::SHOWCASE => [
                'title' => __('showcase'),
                'icon'  => 'fal fa-tachometer-alt',
            ],
            FulfilmentCustomerTabsEnum::AGREED_PRICES => [
                'title' => __('agreed prices'),
                'icon'  => 'fal fa-usd-circle',
            ],

            //            FulfilmentCustomerTabsEnum::NOTE => [
            //                'title' => __('note'),
            //                'icon'  => 'fal fa-sticky-note',
            //            ],
        };
    }
}
