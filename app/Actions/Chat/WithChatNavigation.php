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
     * and parameters. The order is the same for everybody; only where they land differs, since
     * an agent opens on the conversations and everybody else on the figures.
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

        $reports = $section('Reports', ['fal', 'fa-chart-line'], 'reports');
        $inbox     = $section('Customer Inbox', ['fal', 'fa-inbox'], 'inbox');
        $settings  = $section('Settings', ['fal', 'fa-sliders-h'], 'settings');

        return [
            'label'   => __('Chat'),
            'icon'    => ['fal', 'fa-comment-alt'],
            'root'    => $rootPrefix,
            'route'   => $isChatAgent ? $inbox['route'] : $reports['route'],
            'topMenu' => [
                'subSections' => [$inbox, $reports, $settings],
            ],
        ];
    }
}
