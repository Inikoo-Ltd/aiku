<?php

namespace App\Enums\HumanResources\Employee;

use App\Enums\EnumHelperTrait;

enum EmploymentTypeEnum: string
{
    use EnumHelperTrait;

    case PARTTIME = 'part-time';
    case FULLTIME = 'full-time';
    case FREELANCE = 'freelance';

    public static function labels(): array
    {
        return [
            'part-time' => __('Part Time'),
            'full-time' => __('Full Time'),
            'freelance' => __('Freelance'),
        ];
    }
}
