<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 31-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Enums\UI\Accounting;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum InvoicesTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case INVOICES = 'invoices';
    case REFUNDS = 'refunds';

    public function blueprint(): array
    {
        return match ($this) {
            InvoicesTabsEnum::INVOICES => [
                'title' => __('Invoices'),
                'icon' => 'fal fa-file-invoice-dollar',
            ],

            InvoicesTabsEnum::REFUNDS => [
                'title' => __('Refunds'),
                'icon' => 'fal fa-arrow-circle-left',
            ],
        };
    }
}
