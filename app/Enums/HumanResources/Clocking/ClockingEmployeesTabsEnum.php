<?php

namespace App\Enums\HumanResources\Clocking;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ClockingEmployeesTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SCAN_QR_CODE = 'scan_qr_code';
    case TIMESHEETS   = 'timesheets';

    public function blueprint(): array
    {
        return match ($this) {
            ClockingEmployeesTabsEnum::SCAN_QR_CODE => [
                'title' => __('Scan QR Code'),
                'icon'  => 'fal fa-qrcode',
            ],
            ClockingEmployeesTabsEnum::TIMESHEETS => [
                'title' => __('Timesheets'),
                'icon'  => 'fal fa-clock',
            ],
        };
    }
}
