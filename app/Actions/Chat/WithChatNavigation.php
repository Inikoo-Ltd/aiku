<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat;

trait WithChatNavigation
{
    /**
     * The chat section is identical in organisation and shop scope apart from its route prefix
     * and parameters. Agents land on the inbox, everybody else on the dashboard.
     *
     * @param  array<int, string>  $parameters
     * @return array<string, mixed>
     */
    protected function getChatNavigation(string $rootPrefix, array $parameters, bool $isChatAgent): array
    {
        $section = fn (string $label, array $icon, string $name): array => [
            'label' => __($label),
            'icon'  => $icon,
            'root'  => $rootPrefix.$name,
            'route' => [
                'name'       => $rootPrefix.$name,
                'parameters' => $parameters,
            ],
        ];

        $dashboard = $section('Dashboard', ['fal', 'fa-comment-alt'], 'dashboard');
        $inbox     = $section('Inbox', ['fal', 'fa-inbox'], 'inbox');
        $settings  = $section('Settings', ['fal', 'fa-sliders-h'], 'settings');

        return [
            'label'   => __('Chat'),
            'icon'    => ['fal', 'fa-comment-alt'],
            'root'    => $rootPrefix,
            'route'   => $isChatAgent ? $inbox['route'] : $dashboard['route'],
            'topMenu' => [
                'subSections' => $isChatAgent
                    ? [$inbox, $dashboard, $settings]
                    : [$dashboard, $inbox, $settings],
            ],
        ];
    }
}
