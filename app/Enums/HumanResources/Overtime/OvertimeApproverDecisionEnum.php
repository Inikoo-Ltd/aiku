<?php

namespace App\Enums\HumanResources\Overtime;

use App\Enums\EnumHelperTrait;

enum OvertimeApproverDecisionEnum: string
{
    use EnumHelperTrait;

    case PENDING  = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public static function labels(): array
    {
        return [
            'pending'  => __('Pending'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
        ];
    }
}
