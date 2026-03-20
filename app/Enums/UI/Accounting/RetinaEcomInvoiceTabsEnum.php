<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Mar 2026 18:49:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Accounting;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RetinaEcomInvoiceTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case INVOICE_TRANSACTIONS = 'invoice_transactions';
    case PAYMENTS = 'payments';
    case REFUNDS = 'refunds';

    public function blueprint(): array
    {
        return match ($this) {
            RetinaEcomInvoiceTabsEnum::REFUNDS => [
                'title' => __('Refunds'),
                'icon'  => 'fal fa-arrow-circle-left',
                'type'  => 'icon',
                'align' => 'right',
            ],
            RetinaEcomInvoiceTabsEnum::PAYMENTS => [
                'title' => __('Payments'),
                'align' => 'right',
                'icon'  => 'fal fa-credit-card',
            ],
            RetinaEcomInvoiceTabsEnum::INVOICE_TRANSACTIONS => [
                'title' => __('Transactions'),
                'icon'  => 'fal fa-expand-arrows',
            ],
        };
    }
}
