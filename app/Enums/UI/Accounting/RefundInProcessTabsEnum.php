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

enum RefundInProcessTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ITEMS_IN_PROCESS = 'items_in_process';
    case HISTORY = 'history';

    case ITEMS = 'items';


    public function blueprint(): array
    {
        return match ($this) {
            RefundInProcessTabsEnum::ITEMS => [
                'align' => 'right',
                'title' => __('Preview'),
                'icon'  => 'fal fa-star-half-alt',
            ],

            RefundInProcessTabsEnum::ITEMS_IN_PROCESS => [
                'title' => __('Invoice transactions'),
                'icon'  => 'fal fa-bars',
            ],


            RefundInProcessTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
