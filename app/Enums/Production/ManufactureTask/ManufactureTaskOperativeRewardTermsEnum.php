<?php

namespace App\Enums\Production\ManufactureTask;

use App\Enums\EnumHelperTrait;

enum ManufactureTaskOperativeRewardTermsEnum: string
{
    use EnumHelperTrait;

    case ABOVE_UPPER_LIMIT = 'above_upper_limit';
    case ABOVE_LOWER_LIMIT = 'above_lower_limit';
    case ALWAYS = 'always';
    case NEVER = 'never';
}
