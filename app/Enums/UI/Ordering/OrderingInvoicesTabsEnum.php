<?php

namespace App\Enums\UI\Ordering;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrderingInvoicesTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ALL = 'all';
    case PAID = 'paid';
    case UNPAID = 'unpaid';

    public function blueprint(): array
    {
        return match ($this) {
            self::ALL => [
                'title' => __('All'),
                'icon'  => 'fal fa-bars',
            ],
            self::PAID => [
                'title' => __('Paid'),
                'icon'  => 'fal fa-check-circle',
            ],
            self::UNPAID => [
                'title' => __('Unpaid'),
                'icon'  => 'fal fa-circle',
            ],
        };
    }
}
