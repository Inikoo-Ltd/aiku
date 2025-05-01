<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Enums\UI\Basket;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum BasketTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case TRANSACTIONS                       = 'transactions';




    public function blueprint(): array
    {
        return match ($this) {

            BasketTabsEnum::TRANSACTIONS => [
                'title' => __('transactions'),
                'icon'  => 'fal fa-bars',
            ],
        };
    }
}
