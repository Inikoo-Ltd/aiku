<?php

/*
 * author Arya Permana - Kirin
 * created on 06-01-2025-14h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\SysAdmin\Guest;

use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\ActionRequest;

trait WithGuestsSubNavigations
{
    protected function getGuestsNavigation(Group $group, ActionRequest $request): array
    {
        return [
            [
                'number' => $group->sysadminStats->number_guests_status_active,
                'label' => __('Active'),
                'route' => [
                    'name' => 'grp.sysadmin.guests.index',
                    'parameters' => [],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-user-circle'],
                    'tooltip' => __('Active Users'),
                ],
            ],
            [
                'number' => $group->sysadminStats->number_guests_status_inactive,
                'label' => __('Inactive'),
                'route' => [
                    'name' => 'grp.sysadmin.guests.inactive.index',
                    'parameters' => [],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-user-slash'],
                    'tooltip' => __('Suspended Users'),
                ],
            ],

            [
                'number' => $group->sysadminStats->number_guests,
                'label' => __('All'),
                'align' => 'right',
                'route' => [
                    'name' => 'grp.sysadmin.guests.all.index',
                    'parameters' => [],
                ],
            ],

        ];
    }
}
