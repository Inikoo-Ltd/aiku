<?php

namespace App\Enums\HumanResources\Overtime;

use App\Enums\EnumHelperTrait;

enum OvertimeRequestSourceEnum: string
{
    use EnumHelperTrait;

    case EMPLOYEE     = 'employee';
    case ADMIN_RECORD = 'admin_record';
    case IMPORT       = 'import';

    public static function labels(): array
    {
        return [
            'employee'     => __('Employee'),
            'admin_record' => __('Recorded by admin'),
            'import'       => __('Imported'),
        ];
    }
}
