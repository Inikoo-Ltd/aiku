<?php

namespace App\Enums\UI\Dispatching;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PerformanceReportTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case OVERVIEW = 'overview';
    case BONUS = 'bonus';

    public function blueprint(): array
    {
        return match ($this) {
            self::OVERVIEW => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-chart-pie',
            ],
            self::BONUS => [
                'title' => __('Bonus'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
        };
    }
}
