<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Apr 2025 01:01:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Accounting;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum FulfilmentInvoiceTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case GROUPED_FULFILMENT_INVOICE_TRANSACTIONS = 'grouped_fulfilment_invoice_transactions';
    case ITEMIZED_FULFILMENT_INVOICE_TRANSACTIONS = 'itemized_fulfilment_invoice_transactions';
    case HISTORY = 'history';
    case PAYMENTS = 'payments';
    case EMAIL = 'email';
    case REFUNDS = 'refunds';

    public function blueprint(): array
    {
        return match ($this) {
            FulfilmentInvoiceTabsEnum::REFUNDS => [
                'title' => __('Refunds'),
                'icon'  => 'fal fa-arrow-circle-left',
                'type'  => 'icon',
                'align' => 'right',
            ],
            FulfilmentInvoiceTabsEnum::PAYMENTS => [
                'title' => __('Payments'),
                'type'  => 'icon',
                'align' => 'right',
                'icon'  => 'fal fa-credit-card',
            ],

            FulfilmentInvoiceTabsEnum::EMAIL => [
                'align' => 'right',
                'title' => __('email'),
                'icon'  => 'fal fa-envelope',
                'type'  => 'icon'
            ],

            FulfilmentInvoiceTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],

            FulfilmentInvoiceTabsEnum::GROUPED_FULFILMENT_INVOICE_TRANSACTIONS => [
                'title' => __('Itemized by rental/service'),
                'icon'  => 'fal fa-bars',
            ],
            FulfilmentInvoiceTabsEnum::ITEMIZED_FULFILMENT_INVOICE_TRANSACTIONS => [
                'title' => __('Itemised by pallets/spaces'),
                'icon'  => 'fal fa-bars',
            ],
        };
    }
}
