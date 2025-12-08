<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Apr 2025 11:27:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Accounting;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum InvoicesInFulfilmentCustomerTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case INVOICES = 'invoices';
    case REFUNDS = 'refunds';
    case IN_PROCESS = 'in_process';

    public function blueprint(): array
    {
        return match ($this) {
            InvoicesInFulfilmentCustomerTabsEnum::INVOICES => [
                'title' => __('Invoices'),
                'icon' => 'fal fa-file-invoice-dollar',
            ],

            InvoicesInFulfilmentCustomerTabsEnum::REFUNDS => [
                'title' => __('Refunds'),
                'icon' => 'fal fa-arrow-circle-left',
            ],

            InvoicesInFulfilmentCustomerTabsEnum::IN_PROCESS => [
                'title' => __('Standalone invoices in process'),
                'icon' => 'fal fa-seedling',
                'align' => 'right',
            ],
        };
    }
}
