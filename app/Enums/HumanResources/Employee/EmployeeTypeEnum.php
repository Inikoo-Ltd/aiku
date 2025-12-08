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
    case VOLUNTEER = 'volunteer';
    case TEMPORAL_WORKER = 'temporal-worker';
    case WORK_EXPERIENCE = 'work-experience';

    public static function labels(): array
    {
        return [
            'employee' => __('Employee'),
            'volunteer' => __('volunteer'),
            'temporal-worker' => __('temporal worker'),
            'work-experience' => __('work experience'),

        ];
    }
}
