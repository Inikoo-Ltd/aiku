<?php

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum AppointmentsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case REQUESTED = 'requested';
    case UPCOMING  = 'upcoming';
    case PAST      = 'past';
    case CANCELLED = 'cancelled';

    public function blueprint(): array
    {
        return match ($this) {
            self::REQUESTED => [
                'title' => __('Requested'),
                'icon'  => 'fal fa-hourglass-half',
            ],
            self::UPCOMING  => [
                'title' => __('Upcoming'),
                'icon'  => 'fal fa-calendar',
            ],
            self::PAST      => [
                'title' => __('Past'),
                'icon'  => 'fal fa-history',
            ],
            self::CANCELLED => [
                'title' => __('Declined & cancelled'),
                'icon'  => 'fal fa-times-circle',
            ],
        };
    }
}
