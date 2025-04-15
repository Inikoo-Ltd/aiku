<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Apr 2025 01:01:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Accounting;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RetinaFulfilmentInvoiceTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case GROUPED_FULFILMENT_INVOICE_TRANSACTIONS = 'grouped_fulfilment_invoice_transactions';
    case ITEMIZED_FULFILMENT_INVOICE_TRANSACTIONS = 'itemized_fulfilment_invoice_transactions';
    case HISTORY = 'history';
    case PAYMENTS = 'payments';
    case REFUNDS = 'refunds';

    public function blueprint(): array
    {
        return match ($this) {
            RetinaFulfilmentInvoiceTabsEnum::REFUNDS => [
                'title' => __('Refunds'),
                'icon'  => 'fal fa-arrow-circle-left',
                'type'  => 'icon',
                'align' => 'right',
            ],
            RetinaFulfilmentInvoiceTabsEnum::PAYMENTS => [
                'title' => __('Payments'),
                'type'  => 'icon',
                'align' => 'right',
                'icon'  => 'fal fa-credit-card',
            ],
            RetinaFulfilmentInvoiceTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],

            RetinaFulfilmentInvoiceTabsEnum::GROUPED_FULFILMENT_INVOICE_TRANSACTIONS => [
                'title' => __('Itemized by rental/service'),
                'icon'  => 'fal fa-bars',
            ],
            RetinaFulfilmentInvoiceTabsEnum::ITEMIZED_FULFILMENT_INVOICE_TRANSACTIONS => [
                'title' => __('Itemised by pallets/spaces'),
                'icon'  => 'fal fa-bars',
            ],
        };
    }
}
