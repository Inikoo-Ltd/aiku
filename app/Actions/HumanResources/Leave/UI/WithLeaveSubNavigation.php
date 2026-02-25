<?php

namespace App\Actions\HumanResources\Leave\UI;

use Lorisleiva\Actions\ActionRequest;

trait WithLeaveSubNavigation
{
    protected function getLeaveSubNavigation(ActionRequest $request): array
    {
        return [
            [
                'label'    => __('Dashboard'),
                'route'    => [
                    'name'       => 'grp.org.hr.leaves.dashboard',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-chart-network'],
                    'tooltip' => __('Dashboard'),
                ],
            ],
            [
                'label'    => __('Leave Requests'),
                'route'    => [
                    'name'       => 'grp.org.hr.leaves.index',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-calendar-minus'],
                    'tooltip' => __('Leave requests'),
                ],
            ],
        ];
    }
}
