<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 31-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\TrafficSource\UI;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum TrafficSourceTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case CUSTOMERS = 'customers';

    public function blueprint(): array
    {
        return match ($this) {
            TrafficSourceTabsEnum::CUSTOMERS => [
                'title' => __('Customers'),
                'icon'  => 'fal fa-users',
            ],
        };
    }
}
