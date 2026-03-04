<?php

namespace App\Enums\HumanResources\Leave;

use App\Enums\EnumHelperTrait;

enum LeaveTypeEnum: string
{
    use EnumHelperTrait;

    case ANNUAL = 'annual';
    case MEDICAL = 'medical';
    case UNPAID = 'unpaid';
    case HALFDAY_MORNING = 'halfday-morning';
    case HALFDAY_AFTERNOON = 'halfday-afternoon';
    case TRAINING = 'training';
    case LEAVE_OF_ABSENCE = 'leave-of-absence';
    case COMPASSIONATE = 'compassionate';
    case PARENTAL = 'parental';
    case SABBATICAL = 'sabbatical';

    public static function labels(): array
    {
        return [
            'annual'             => __('Annual Leave'),
            'medical'            => __('Medical Leave'),
            'unpaid'             => __('Unpaid Leave'),
            'halfday-morning'    => __('Half Day Morning'),
            'halfday-afternoon'  => __('Half Day Afternoon'),
            'training'           => __('Training Leave'),
            'leave-of-absence'   => __('Leave of Absence'),
            'compassionate'       => __('Compassionate Leave'),
            'parental'           => __('Parental Leave'),
            'sabbatical'         => __('Sabbatical'),
        ];
    }

    public static function colors(): array
    {
        return [
            'annual'             => 'blue',
            'medical'            => 'yellow',
            'unpaid'             => 'fuchsia',
            'halfday-morning'    => 'green',
            'halfday-afternoon'  => 'green',
            'training'           => 'purple',
            'leave-of-absence'   => 'orange',
            'compassionate'       => 'pink',
            'parental'           => 'cyan',
            'sabbatical'         => 'indigo',
        ];
    }

    public function color(): string
    {
        return self::colors()[$this->value] ?? 'gray';
    }
}
