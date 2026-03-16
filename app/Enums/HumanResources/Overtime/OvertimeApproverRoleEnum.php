<?php

namespace App\Enums\HumanResources\Overtime;

use App\Enums\EnumHelperTrait;

enum OvertimeApproverRoleEnum: string
{
    use EnumHelperTrait;

    case MANAGER = 'manager';
    case HR      = 'hr';
    case ADMIN   = 'admin';

    public static function labels(): array
    {
        return [
            'manager' => __('Manager'),
            'hr'      => __('HR'),
            'admin'   => __('Admin'),
        ];
    }
}
