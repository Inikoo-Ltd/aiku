<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 02:18:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\HumanResources\Employee;

use App\Enums\EnumHelperTrait;

enum EmployeeTypeEnum: string
{
    use EnumHelperTrait;

    case EMPLOYEE = 'employee';
    case PARTTIME = 'part-time';
    case FULLTIME = 'full-time';
    case INTERNSHIP = 'internship';
    case VOLUNTEER = 'volunteer';
    case TEMPORAL_WORKER = 'temporal-worker';
    case WORK_EXPERIENCE = 'work-experience';

    public static function labels(): array
    {
        return [
            'employee' => __('Employee'),
            'volunteer' => __('Volunteer'),
            'temporal-worker' => __('Temporal Worker'),
            'work-experience' => __('Work Experience'),
            'internship' => __('Internship'),
            'full-time' => __('Full Time'),
            'part-time' => __('Part Time'),
        ];
    }

}
