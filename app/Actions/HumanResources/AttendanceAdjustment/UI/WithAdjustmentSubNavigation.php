<?php

namespace App\Actions\HumanResources\AttendanceAdjustment\UI;

use Lorisleiva\Actions\ActionRequest;

trait WithAdjustmentSubNavigation
{
    protected function getAdjustmentSubNavigation(ActionRequest $request): array
    {
        return [
            [
                'label'    => __('Dashboard'),
                'route'    => [
                    'name'       => 'grp.org.hr.adjustments.dashboard',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-tachometer-alt'],
                    'tooltip' => __('Dashboard'),
                ],
            ],
            [
                'label'    => __('Adjustment Requests'),
                'route'    => [
                    'name'       => 'grp.org.hr.adjustments.index',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-list'],
                    'tooltip' => __('Adjustment requests'),
                ],
            ],
        ];
    }
}
