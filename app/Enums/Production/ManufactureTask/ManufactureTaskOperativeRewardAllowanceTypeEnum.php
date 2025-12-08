<?php

namespace App\Enums\Production\ManufactureTask;

use App\Enums\EnumHelperTrait;

enum ManufactureTaskOperativeRewardAllowanceTypeEnum: string
{
    use EnumHelperTrait;

    case ON_TOP_SALARY = 'on_top_salary';
    case OFFSET_SALARY = 'offset_salary';
}
