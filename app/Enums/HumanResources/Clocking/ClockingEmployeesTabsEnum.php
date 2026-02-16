<?php

namespace App\Enums\HumanResources\Clocking;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ClockingEmployeesTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SCAN_QR_CODE = 'scan_qr_code';
    case TIMESHEETS = 'timesheets';
    case LEAVES = 'leaves';
    case ADJUSTMENTS = 'adjustments';

    public function blueprint(): array
    {
        return match ($this) {
            ClockingEmployeesTabsEnum::SCAN_QR_CODE => [
                'title' => __('Scan QR Code'),
                'icon' => 'fal fa-qrcode',
            ],
            ClockingEmployeesTabsEnum::TIMESHEETS => [
                'icon' => 'fal fa-clock',
                'title' => __('Timesheets'),
            ],
            ClockingEmployeesTabsEnum::LEAVES => [
                'icon' => 'fal fa-calendar-minus',
                'title' => __('Leave'),
            ],
            ClockingEmployeesTabsEnum::ADJUSTMENTS => [
                'icon' => 'fal fa-edit',
                'title' => __('Adjustments'),
            ],
        };
    }
}
