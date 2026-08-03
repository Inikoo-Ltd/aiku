<?php

namespace App\Enums\Catalogue\Review;

use App\Enums\EnumHelperTrait;

enum ReviewMediaTypeEnum: string
{
    use EnumHelperTrait;

    case IMAGE = 'image';
    case VIDEO = 'video';
}
