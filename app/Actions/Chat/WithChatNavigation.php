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
     * and parameters. The inbox is an agent's rota, so only an agent is given it; overseeing
     * has its own page, given to whoever oversees and to anybody who is not an agent at all,
     * since that is the only way they have of looking at a conversation.
     *
     * @param  array<int, string>  $parameters
     * @return array<string, mixed>
     */
    protected function getChatNavigation(string $rootPrefix, array $parameters, bool $isChatAgent, bool $isChatSupervisor = false): array
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

        $reports     = $section('Reports', ['fal', 'fa-chart-line'], 'reports');
        $inbox       = $section('Customer Inbox', ['fal', 'fa-inbox'], 'inbox');
        $supervision = $section('Supervision', ['fal', 'fa-user-headset'], 'supervision');
        $settings    = $section('Settings', ['fal', 'fa-sliders-h'], 'settings');

        return [
            'label'   => __('Chat'),
            'icon'    => ['fal', 'fa-comment-alt'],
            'root'    => $rootPrefix,
            'route'   => $isChatAgent ? $inbox['route'] : $supervision['route'],
            'topMenu' => [
                'subSections' => array_values(array_filter([
                    $isChatAgent ? $inbox : null,
                    $isChatSupervisor || !$isChatAgent ? $supervision : null,
                    $reports,
                    $settings,
                ])),
            ],
        ];
    }
}
