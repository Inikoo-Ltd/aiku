<?php

namespace App\Actions\HumanResources\Calendar;

trait WithCalendarSubNavigation
{
    protected function getCalendarSubNavigation(): array
    {
        $request = request();

        return [
            [
                'label'    => __('Calendar'),
                'route'    => [
                    'name'       => 'grp.org.hr.calendars.index',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-calendar'],
                    'tooltip' => __('Calendar'),
                ],
            ],
            [
                'label'    => __('Holiday'),
                'route'    => [
                    'name'       => 'grp.org.hr.holidays.index',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-umbrella'],
                    'tooltip' => __('Holiday'),
                ],
            ]
        ];
    }
}
