<?php

namespace App\Enums\HumanResources\Clocking;

use App\Enums\EnumHelperTrait;

enum ClockingActionEnum: string
{
    use EnumHelperTrait;

    case CLOCK_IN  = 'clock_in';
    case CLOCK_OUT = 'clock_out';
    case BREAK_START = 'break_start';
    case BREAK_END   = 'break_end';

    public function label(): string
    {
        return match ($this) {
            self::CLOCK_IN  => __('Clock In'),
            self::CLOCK_OUT => __('Clock Out'),
            self::BREAK_START => __('Break Start'),
            self::BREAK_END   => __('Break End'),
        };
    }
}
