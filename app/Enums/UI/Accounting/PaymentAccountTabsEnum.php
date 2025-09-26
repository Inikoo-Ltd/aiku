<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Apr 2024 20:08:38 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Accounting;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PaymentAccountTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case OVERVIEW         = 'overview';
    case STATS            = 'stats';
    // case PAYMENTS         = 'payments';
    case HISTORY          = 'history';


    public function blueprint(): array
    {
        return match ($this) {
            PaymentAccountTabsEnum::STATS             => [
                'title' => __('Stats'),
                'icon'  => 'fal fa-chart-line',
            ],
            // PaymentAccountTabsEnum::PAYMENTS     => [
            //     'title' => __('Payments'),
            //     'icon'  => 'fal fa-coins',
            // ],
            PaymentAccountTabsEnum::HISTORY     => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            PaymentAccountTabsEnum::OVERVIEW => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt',
            ],
        };
    }
}
