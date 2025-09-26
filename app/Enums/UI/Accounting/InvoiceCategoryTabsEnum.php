<?php

/*
 * author Arya Permana - Kirin
 * created on 18-02-2025-11h-10m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\UI\Accounting;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum InvoiceCategoryTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case OVERVIEW         = 'overview';
    case STATS            = 'stats';
    case HISTORY          = 'history';


    public function blueprint(): array
    {
        return match ($this) {
            InvoiceCategoryTabsEnum::STATS       => [
                'title' => __('Stats'),
                'icon'  => 'fal fa-chart-line',
            ],
            InvoiceCategoryTabsEnum::HISTORY     => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            InvoiceCategoryTabsEnum::OVERVIEW => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt',
            ],
        };
    }
}
