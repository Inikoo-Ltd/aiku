<?php

namespace App\Enums\HumanResources\Clocking;

use App\Enums\EnumHelperTrait;

enum ClockingActionEnum: string
{
    use EnumHelperTrait;

    case CHECK_IN  = 'check_in';
    case CHECK_OUT = 'check_out';
    case BREAK_START = 'break_start';
    case BREAK_END   = 'break_end';

    public function label(): string
    {
        return match ($this) {
            self::CHECK_IN  => __('Check In'),
            self::CHECK_OUT => __('Check Out'),
            self::BREAK_START => __('Break Start'),
            self::BREAK_END   => __('Break End'),
        };
    }
}
