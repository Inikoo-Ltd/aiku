<?php

namespace App\Enums\HumanResources\Overtime;

use App\Enums\EnumHelperTrait;

enum OvertimeRequestStatusEnum: string
{
    use EnumHelperTrait;

    case PENDING   = 'pending';
    case APPROVED  = 'approved';
    case REJECTED  = 'rejected';
    case CANCELLED = 'cancelled';
    case RECORDED  = 'recorded';

    public static function labels(): array
    {
        return [
            'pending'   => __('Pending'),
            'approved'  => __('Approved'),
            'rejected'  => __('Rejected'),
            'cancelled' => __('Cancelled'),
            'recorded'  => __('Recorded'),
        ];
    }
}
