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
            ],
            [
                'label'    => __('Holiday Year'),
                'route'    => [
                    'name'       => 'grp.org.hr.holiday_years.index',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-calendar-alt'],
                    'tooltip' => __('Holiday Year'),
                ],
            ]
        ];
    }
}
