<?php

namespace App\Actions\UI\Notification;

use Lorisleiva\Actions\ActionRequest;

trait WithNotificationSubNavigation
{
    protected function getNotificationSubNavigation(ActionRequest $request): array
    {
        return [
            [
                'label'    => __('User settings'),
                'route'    => [
                    'name'       => 'grp.sysadmin.notification-settings.users',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-user-circle'],
                    'tooltip' => __('User settings'),
                ],
            ],
        ];
    }
}
