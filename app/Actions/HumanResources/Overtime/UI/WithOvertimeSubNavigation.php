<?php

namespace App\Actions\HumanResources\Overtime\UI;

use Lorisleiva\Actions\ActionRequest;

trait WithOvertimeSubNavigation
{
    protected function getOvertimeSubNavigation(ActionRequest $request): array
    {
        return [
            [
                'label'    => __('Dashboard'),
                'route'    => [
                    'name'       => 'grp.org.hr.overtime.dashboard',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-tachometer-alt'],
                    'tooltip' => __('Dashboard'),
                ],
            ],
            [
                'label'    => __('Overtime'),
                'route'    => [
                    'name'       => 'grp.org.hr.overtime.index',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-list'],
                    'tooltip' => __('Overtime list'),
                ],
            ],
            [
                'label'    => __('Overtime types'),
                'route'    => [
                    'name'       => 'grp.org.hr.overtime_types.index',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-layer-group'],
                    'tooltip' => __('Overtime types'),
                ],
            ],
        ];
    }
}
