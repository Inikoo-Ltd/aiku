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
    case ABSENT = 'absent';
    case BIRTHDAY = 'birthday';
    case HOLIDAY = 'holiday';
    case HOLIDAY_VACATION = 'holiday-vacation';
    case LATE_FOR_WORK = 'late-for-work';
    case MEETING = 'meeting';
    case OUT_OF_OFFICE = 'out-of-office';
    case PARENTAL_MATERNITY_PATERNITY = 'parental-maternity-paternity';
    case SICK_LEAVE = 'sick-leave';
    case UNPAID_LEAVE = 'unpaid-leave';

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
            'absent'             => __('Absent'),
            'birthday'           => __('Birthday'),
            'holiday'            => __('Holiday'),
            'holiday-vacation'   => __('Holiday / Vacation'),
            'late-for-work'      => __('Late for Work'),
            'meeting'            => __('Meeting'),
            'out-of-office'      => __('Out of Office (Work Related)'),
            'parental-maternity-paternity' => __('Parental (Maternity/Paternity)'),
            'sick-leave'        => __('Sick Leave'),
            'unpaid-leave'      => __('Un-Paid Leave'),
        ];
    }

    public static function colors(): array
    {
        return [
            'annual'             => 'green',
            'medical'            => 'orange',
            'unpaid'             => 'black',
            'halfday-morning'    => 'green',
            'halfday-afternoon'  => 'green',
            'training'           => 'purple',
            'leave-of-absence'   => 'orange',
            'compassionate'       => 'pink',
            'parental'           => 'cyan',
            'sabbatical'         => 'indigo',
            'absent'             => 'gray',
            'birthday'           => 'pink',
            'holiday'            => 'green',
            'holiday-vacation'   => 'green',
            'late-for-work'      => 'orange',
            'meeting'            => 'blue',
            'out-of-office'      => 'gray',
            'parental-maternity-paternity' => 'cyan',
            'sick-leave'        => 'red',
            'unpaid-leave'      => 'black',
        ];
    }

    public static function shortCodes(): array
    {
        return [
            'annual'             => 'H',
            'medical'            => 'S',
            'unpaid'             => 'U',
            'halfday-morning'    => 'HM',
            'halfday-afternoon'  => 'HA',
            'training'           => 'T',
            'leave-of-absence'   => 'LA',
            'compassionate'       => 'C',
            'parental'           => 'P',
            'sabbatical'         => 'SA',
            'absent'             => 'A',
            'birthday'           => 'B',
            'holiday'            => 'V',
            'holiday-vacation'   => 'HV',
            'late-for-work'      => 'L',
            'meeting'            => 'M',
            'out-of-office'      => 'OOO',
            'parental-maternity-paternity' => 'PMP',
            'sick-leave'        => 'SL',
            'unpaid-leave'      => 'UP',
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value] ?? '';
    }

    public function color(): string
    {
        return self::colors()[$this->value] ?? 'gray';
    }

    public function shortCode(): string
    {
        return self::shortCodes()[$this->value] ?? '';
    }
}
