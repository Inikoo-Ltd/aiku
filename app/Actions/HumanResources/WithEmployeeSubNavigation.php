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
            'route' => [
                'name'      => 'grp.org.hr.employees.show',
                'parameters' => $request->route()->originalParameters()
            ],
            'label'     => __('Employee'),
            'leftIcon'  => [
                'icon'    => 'fal fa-stream',
                'tooltip' => __('employee'),
            ],

        ];


        $subNavigation[] = [
            'route' => [
                'name'      => 'grp.org.hr.employees.show.users.index',
                'parameters' => $request->route()->originalParameters()

            ],

            'label'     => __('Users'),
            'leftIcon'  => [
                'icon'    => 'fal fa-user',
                'tooltip' => __('Users'),
            ],
            'number' => $employee->users()->count()

        ];

        $subNavigation[] = [
            'route' => [
                'name'      => 'grp.org.hr.employees.show.positions.index',
                'parameters' => $request->route()->originalParameters()

            ],

            'label'     => __('Responsibilities'),
            'leftIcon'  => [
                'icon'    => 'fal fa-clipboard-list-check',
                'tooltip' => __('Responsibilities'),
            ],
            'number' => $employee->stats->number_job_positions

        ];

        $subNavigation[] = [
            'route' => [
                'name'      => 'grp.org.hr.employees.show.timesheets.index',
                'parameters' => $request->route()->originalParameters()

            ],

            'label'     => __('Timesheets'),
            'leftIcon'  => [
                'icon'    => 'fal fa-stopwatch',
                'tooltip' => __('Timesheets'),
            ],
            'number' => $employee->stats->number_timesheets

        ];

        return $subNavigation;
    }

}
