<?php

namespace App\Actions\HumanResources\Leave\UI;

use Lorisleiva\Actions\ActionRequest;

trait WithLeaveSubNavigation
{
    protected function getLeaveSubNavigation(ActionRequest $request): array
    {
        return [
            [
                'label' => __('Dashboard'),
                'route' => [
                    'name' => 'grp.org.hr.leaves.dashboard',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-chart-network'],
                    'tooltip' => __('Dashboard'),
                ],
            ],
            [
                'label' => __('Leave Requests'),
                'route' => [
                    'name' => 'grp.org.hr.leaves.index',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-calendar-minus'],
                    'tooltip' => __('Leave Requests'),
                ],
            ],
            [
                'label' => __('Leave Types'),
                'route' => [
                    'name' => 'grp.org.hr.leaves.types.index',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-layer-group'],
                    'tooltip' => __('Leave Types'),
                ],
            ],
            [
                'label' => __('Leave Concurrency Rules'),
                'route' => [
                    'name' => 'grp.org.hr.leave_concurrency_rules.index',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-project-diagram'],
                    'tooltip' => __('Leave Concurrency Rules'),
                ],
            ],
        ];
    }
}
