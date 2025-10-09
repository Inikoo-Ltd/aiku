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
                'title' => __('Transactions'),
                'icon'  => 'fal fa-bars',
            ],
            OrderTabsEnum::PAYMENTS => [
                'type'  => 'icon',
                'align' => 'right',
                'title' => __('Payments'),
                'icon'  => 'fal fa-dollar-sign',
            ],

            // OrderTabsEnum::SENT_EMAILS => [
            //     'title' => __('Sent emails'),
            //     'icon'  => 'fal fa-envelope',
            //     'type'  => 'icon',
            //     'align' => 'right',

            // ],
            // OrderTabsEnum::DISCOUNTS => [
            //     'title' => __('Discounts'),
            //     'icon'  => 'fal fa-tag',
            //     'type'  => 'icon',
            //     'align' => 'right',

            // ],
            OrderTabsEnum::INVOICES => [
                'title' => __('Invoices'),
                'icon'  => 'fal fa-file-invoice-dollar',
                'type'  => 'icon',
                'align' => 'right',

            ],
            OrderTabsEnum::DELIVERY_NOTES => [
                'title' => __('Delivery notes'),
                'icon'  => 'fal fa-truck',
                'type'  => 'icon',
                'align' => 'right',
            ],
            OrderTabsEnum::ATTACHMENTS => [
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon',
                'align' => 'right',
            ],
            //OrderTabsEnum::HISTORY => [
            //     'title' => __('History'),
            //     'icon'  => 'fal fa-clock',
            //     'type'  => 'icon',
            //     'align' => 'right',
            // ],

        };
    }
}
