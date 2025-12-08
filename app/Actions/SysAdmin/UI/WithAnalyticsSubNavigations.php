<?php

/*
 * author Arya Permana - Kirin
 * created on 06-01-2025-15h-05m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\SysAdmin\UI;

use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\ActionRequest;

trait WithAnalyticsSubNavigations
{
    protected function getAnalyticsNavigation(Group $group, ActionRequest $request): array
    {
        return [
            [
                'label' => __('Dashboard'),
                'route' => [
                    'name' => 'grp.sysadmin.analytics.dashboard',
                    'parameters' => [],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-tachometer-alt'],
                    'tooltip' => __('Dashboard'),
                ],
            ],
            [
                'number' => $group->sysadminStats->number_user_requests,
                'label' => __('User Requests'),
                'route' => [
                    'name' => 'grp.sysadmin.analytics.request.index',
                    'parameters' => [],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-user-slash'],
                    'tooltip' => __('Suspended Users'),
                ],
            ],
        ];
    }
}
