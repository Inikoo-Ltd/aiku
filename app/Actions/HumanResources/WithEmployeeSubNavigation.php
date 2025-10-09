<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 May 2024 18:12:15 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources;

use App\Models\HumanResources\Employee;
use Lorisleiva\Actions\ActionRequest;

trait WithEmployeeSubNavigation
{
    protected function getEmployeeSubNavigation(Employee $employee, ActionRequest $request): array
    {
        $subNavigation = [];

        $subNavigation[] = [
            'isAnchor' => true,
            'route'    => [
                'name'       => 'grp.org.hr.employees.show',
                'parameters' => $request->route()->originalParameters()
            ],
            'label'    => __('Employee'),
            'leftIcon' => [
                'icon'    => 'fal fa-stream',
                'tooltip' => __('Employee'),
            ],

        ];

        if ($employee->user_id) {
            $subNavigation[] = [
                'route' => [
                    'name'       => 'grp.org.hr.employees.show.users.show',
                    'parameters' => array_merge(
                        $request->route()->originalParameters(),
                        ['user' => $employee->user->slug]
                    )

                ],

                'label'    => __('User'),
                'leftIcon' => [
                    'icon'    => 'fal fa-user-circle',
                    'tooltip' => __('User'),
                ],
                'number'   => $employee->users()->count()

            ];
        }


        $subNavigation[] = [
            'route' => [
                'name'       => 'grp.org.hr.employees.show.timesheets.index',
                'parameters' => $request->route()->originalParameters()

            ],

            'label'    => __('Timesheets'),
            'leftIcon' => [
                'icon'    => 'fal fa-stopwatch',
                'tooltip' => __('Timesheets'),
            ],
            'number'   => $employee->stats->number_timesheets

        ];

        return $subNavigation;
    }

}
