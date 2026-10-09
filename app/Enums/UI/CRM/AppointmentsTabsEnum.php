<?php

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum AppointmentsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case UPCOMING  = 'upcoming';
    case PAST      = 'past';
    case CANCELLED = 'cancelled';

    public function blueprint(): array
    {
        return match ($this) {
            self::UPCOMING  => [
                'title' => __('Upcoming'),
                'icon'  => 'fal fa-calendar',
            ],
            self::PAST      => [
                'title' => __('Past'),
                'icon'  => 'fal fa-history',
            ],
            self::CANCELLED => [
                'title' => __('Cancelled'),
                'icon'  => 'fal fa-times-circle',
            ],
        };
    }
}
