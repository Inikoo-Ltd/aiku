<?php

/*
 * author Arya Permana - Kirin
 * created on 07-01-2025-10h-05m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\SysAdmin\Guest;

use App\Models\SysAdmin\Guest;
use Lorisleiva\Actions\ActionRequest;

trait WithGuestSubNavigations
{
    protected function getGuestNavigation(Guest $guest, ActionRequest $request): array
    {
        return [
            [
                'label' => __($guest->contact_name),
                'route' => [
                    'name' => 'grp.sysadmin.guests.show',
                    'parameters' => [
                        'guest' => $guest->slug,
                    ],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-user-alien'],
                    'tooltip' => __('Guest'),
                ],
            ],
            [
                'label' => __('Requests'),
                'route' => [
                    // "name"       => "grp.sysadmin.analytics.request.index",
                    // "parameters" => [
                    //     'user' => $user->username
                    // ],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-clock'],
                    'tooltip' => __('Requests'),
                ],
            ],

        ];
    }
}
