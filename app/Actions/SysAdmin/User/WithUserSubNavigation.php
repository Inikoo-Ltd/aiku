<?php

/*
 * author Arya Permana - Kirin
 * created on 24-12-2024-14h-17m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\SysAdmin\User;

use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

trait WithUserSubNavigation
{
    protected function getUserNavigation(User $user, ActionRequest $request): array
    {
        return [
            [
                "label"    => __($user->username),
                "route"     => [
                    "name"       => "grp.sysadmin.users.show",
                    "parameters" => [
                        'user' => $user->slug
                    ],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-user"],
                    "tooltip" => __("User"),
                ],
            ],
            [
                "label"    => __("Visit Logs"),
                "route"     => [
                    "name"       => "grp.sysadmin.users.show.visit_logs.index",
                    "parameters" => [
                        'user' => $user->slug
                    ],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-location-dot"],
                    "tooltip" => __("Visit Logs"),
                ],
            ],
            [
                "label"    => __("Actions"),
                "route"     => [
                    "name"       => "grp.sysadmin.users.show.actions.index",
                    "parameters" => [
                        'user' => $user->slug
                    ],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-clock"],
                    "tooltip" => __("Actions"),
                ],
            ],
        ];
    }
}
