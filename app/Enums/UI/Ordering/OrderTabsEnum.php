<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 09:28:41 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Ordering;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrderTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case TRANSACTIONS                       = 'transactions';
    case PAYMENTS                    = 'payments';
    // case DISCOUNTS                   = 'discounts';
    case INVOICES                    = 'invoices';
    case DELIVERY_NOTES              = 'delivery_notes';

    // case HISTORY                     = 'history';

    case ATTACHMENTS                 = 'attachments';

    // case SENT_EMAILS                 = 'sent_emails';






    public function blueprint(): array
    {
        return match ($this) {

            OrderTabsEnum::TRANSACTIONS => [
                'title' => __('transactions'),
                'icon'  => 'fal fa-bars',
            ],
            OrderTabsEnum::PAYMENTS => [
                'type'  => 'icon',
                'align' => 'right',
                'title' => __('payments'),
                'icon'  => 'fal fa-dollar-sign',
            ],

            // OrderTabsEnum::SENT_EMAILS => [
            //     'title' => __('sent emails'),
            //     'icon'  => 'fal fa-envelope',
            //     'type'  => 'icon',
            //     'align' => 'right',

            // ],
            // OrderTabsEnum::DISCOUNTS => [
            //     'title' => __('discounts'),
            //     'icon'  => 'fal fa-tag',
            //     'type'  => 'icon',
            //     'align' => 'right',

            // ],
            OrderTabsEnum::INVOICES => [
                'title' => __('invoices'),
                'icon'  => 'fal fa-file-invoice-dollar',
                'type'  => 'icon',
                'align' => 'right',

            ],
            OrderTabsEnum::DELIVERY_NOTES => [
                'title' => __('delivery notes'),
                'icon'  => 'fal fa-truck',
                'type'  => 'icon',
                'align' => 'right',
            ],
            OrderTabsEnum::ATTACHMENTS => [
                'title' => __('attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon',
                'align' => 'right',
            ],
            //OrderTabsEnum::HISTORY => [
            //     'title' => __('history'),
            //     'icon'  => 'fal fa-clock',
            //     'type'  => 'icon',
            //     'align' => 'right',
            // ],

        };
    }
}
