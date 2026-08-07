<?php

namespace App\Enums\UI\HumanResources;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum TimesheetEmployeeViewEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case OVERVIEW      = 'overview';
    case CLOCKED       = 'clocked';
    case OVERTIME      = 'overtime';
    case PAID_TIME     = 'paid_time';
    case PAID_OVERTIME = 'paid_overtime';
    case WORKED        = 'worked';

    public function blueprint(): array
    {
        return match ($this) {
            TimesheetEmployeeViewEnum::OVERVIEW => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-th-list',
            ],
            TimesheetEmployeeViewEnum::CLOCKED => [
                'title' => __('Clocked'),
                'icon'  => 'fal fa-stopwatch',
            ],
            TimesheetEmployeeViewEnum::OVERTIME => [
                'title' => __('Overtime'),
                'icon'  => 'fal fa-exclamation-circle',
            ],
            TimesheetEmployeeViewEnum::PAID_TIME => [
                'title' => __('Paid time'),
                'icon'  => 'fal fa-sack-dollar',
            ],
            TimesheetEmployeeViewEnum::PAID_OVERTIME => [
                'title' => __('Paid overtime'),
                'icon'  => 'fal fa-coins',
            ],
            TimesheetEmployeeViewEnum::WORKED => [
                'title' => __('Worked'),
                'icon'  => 'fal fa-briefcase',
            ],
        };
    }

    public function sourceColumn(): ?string
    {
        return match ($this) {
            TimesheetEmployeeViewEnum::CLOCKED, TimesheetEmployeeViewEnum::WORKED => 'working_duration',
            TimesheetEmployeeViewEnum::OVERTIME => 'unpaid_overtime_duration',
            TimesheetEmployeeViewEnum::PAID_TIME => 'paid_duration',
            TimesheetEmployeeViewEnum::PAID_OVERTIME => 'paid_overtime_duration',
            TimesheetEmployeeViewEnum::OVERVIEW => null,
        };
    }
}
