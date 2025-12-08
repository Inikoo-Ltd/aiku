<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Enums\UI\Accounting;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RefundTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ITEMS = 'items';
    case HISTORY = 'history';
    case PAYMENTS = 'payments';

    public function blueprint(): array
    {
        return match ($this) {

            RefundTabsEnum::PAYMENTS => [
                'title' => __('Payments'),
                'type' => 'icon',
                'align' => 'right',
                'icon' => 'fal fa-credit-card',
            ],

            RefundTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],

            RefundTabsEnum::ITEMS => [
                'title' => __('Items'),
                'icon' => 'fal fa-bars',
            ],

        };
    }
}
