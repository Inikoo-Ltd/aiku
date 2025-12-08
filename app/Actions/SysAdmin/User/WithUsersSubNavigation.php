<?php

/*
 * author Arya Permana - Kirin
 * created on 24-12-2024-14h-17m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\SysAdmin\User;

use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\ActionRequest;

trait WithUsersSubNavigation
{
    protected function getUsersNavigation(Group $group, ActionRequest $request): array
    {
        return [
            [
                'number' => $group->sysadminStats->number_users_status_active,
                'label' => __('Active'),
                'route' => [
                    'name' => 'grp.sysadmin.users.index',
                    'parameters' => [],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-user-circle'],
                    'tooltip' => __('Active Users'),
                ],
            ],
            [
                'number' => $group->sysadminStats->number_users_status_inactive,
                'label' => __('Suspended'),
                'route' => [
                    'name' => 'grp.sysadmin.users.suspended.index',
                    'parameters' => [],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-user-slash'],
                    'tooltip' => __('Suspended Users'),
                ],
            ],

            [
                'number' => $group->sysadminStats->number_users,
                'label' => __('All'),
                'align' => 'right',
                'route' => [
                    'name' => 'grp.sysadmin.users.all.index',
                    'parameters' => [],
                ],
            ],

        ];
    }
}
