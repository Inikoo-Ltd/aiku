<?php

namespace App\Enums\Catalogue\Review;

use App\Enums\EnumHelperTrait;

enum ReviewReplyReplierTypeEnum: string
{
    use EnumHelperTrait;

    case Merchant = 'merchant';
    case Admin = 'admin';
    case System = 'system';
}
