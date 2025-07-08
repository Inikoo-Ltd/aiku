<?php

/*
 * author Arya Permana - Kirin
 * created on 07-07-2025-10h-50m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\UI\Accounting;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RetinaDropshippingInvoiceTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ITEMIZED_FULFILMENT_INVOICE_TRANSACTIONS = 'itemized_fulfilment_invoice_transactions';
    case HISTORY = 'history';
    case PAYMENTS = 'payments';
    case REFUNDS = 'refunds';

    public function blueprint(): array
    {
        return match ($this) {
            RetinaDropshippingInvoiceTabsEnum::REFUNDS => [
                'title' => __('Refunds'),
                'icon'  => 'fal fa-arrow-circle-left',
                'type'  => 'icon',
                'align' => 'right',
            ],
            RetinaDropshippingInvoiceTabsEnum::PAYMENTS => [
                'title' => __('Payments'),
                'type'  => 'icon',
                'align' => 'right',
                'icon'  => 'fal fa-credit-card',
            ],
            RetinaDropshippingInvoiceTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            RetinaDropshippingInvoiceTabsEnum::ITEMIZED_FULFILMENT_INVOICE_TRANSACTIONS => [
                'title' => __('Transactions'),
                'icon'  => 'fal fa-expand-arrows',
            ],
        };
    }
}
