<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:13:15 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Accounting;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum InvoiceTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case INVOICE_TRANSACTIONS = 'invoice_transactions';
    case HISTORY = 'history';
    case PAYMENTS = 'payments';
    case EMAIL = 'email';
    case REFUNDS = 'refunds';

    public function blueprint(): array
    {
        return match ($this) {
            InvoiceTabsEnum::REFUNDS => [
                'title' => __('Refunds'),
                'icon'  => 'fal fa-arrow-circle-left',
                'type'  => 'icon',
                'align' => 'right',
            ],
            InvoiceTabsEnum::PAYMENTS => [
                'title' => __('Payments'),
                'type'  => 'icon',
                'align' => 'right',
                'icon'  => 'fal fa-credit-card',
            ],

            InvoiceTabsEnum::EMAIL => [
                'align' => 'right',
                'title' => __('email'),
                'icon'  => 'fal fa-envelope',
                'type'  => 'icon'
            ],

            InvoiceTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],

            InvoiceTabsEnum::INVOICE_TRANSACTIONS => [
                'title' => __('Transactions'),
                'icon'  => 'fal fa-bars',
            ],
        };
    }
}
