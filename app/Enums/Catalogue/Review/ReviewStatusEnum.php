<?php

namespace App\Enums\Catalogue\Review;

use App\Enums\EnumHelperTrait;

enum ReviewStatusEnum: string
{
    use EnumHelperTrait;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public static function labels(): array
    {
        return [
            self::Pending->value => __('Pending'),
            self::Approved->value => __('Approved'),
            self::Rejected->value => __('Rejected'),
        ];
    }
}
