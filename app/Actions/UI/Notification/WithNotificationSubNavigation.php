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
            [
                'label'    => __('Guest settings'),
                'route'    => [
                    'name'       => 'grp.sysadmin.notification-settings.guests',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-user-alien'],
                    'tooltip' => __('Guest settings'),
                ],
            ],
            [
                'label'    => __('Notification types'),
                'route'    => [
                    'name'       => 'grp.sysadmin.notification-settings.types',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'layer-group'],
                    'tooltip' => __('Notification types'),
                ],
            ],
        ];
    }
}
