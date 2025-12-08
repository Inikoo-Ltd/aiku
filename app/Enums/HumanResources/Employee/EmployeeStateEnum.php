<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 02:20:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\HumanResources\Employee;

use App\Enums\EnumHelperTrait;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum EmployeeStateEnum: string
{
    use EnumHelperTrait;

    case HIRED = 'hired';
    case WORKING = 'working';
    case LEAVING = 'leaving';
    case LEFT = 'left';

    public static function labels(): array
    {
        return [
            'hired' => __('Hired'),
            'working' => __('Working'),
            'leaving' => __('Leaving'),
            'left' => __('Left'),
        ];
    }

    public static function count(Organisation|Group|JobPosition $parent): array
    {

        if ($parent instanceof JobPosition) {
            $stats = $parent->stats;
        } else {
            $stats = $parent->humanResourcesStats;
        }

        return [
            'hired' => $stats->number_employees_state_hired,
            'working' => $stats->number_employees_state_working,
            'leaving' => $stats->number_employees_state_leaving,
            'left' => $stats->number_employees_state_left,
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'hired' => [

                'tooltip' => __('hired'),
                'icon' => 'fal fa-hand-holding-seedling',

            ],
            'working' => [
                'tooltip' => __('working'),
                'icon' => 'fal fa-handshake',

            ],
            'leaving' => [
                'tooltip' => __('leaving'),
                'icon' => 'fal fa-handshake-alt-slash',
                'class' => 'animate-pulse',

            ],
            'left' => [
                'tooltip' => __('ex-worker'),
                'icon' => 'fal fa-handshake-alt-slash',

            ],

        ];
    }
}
