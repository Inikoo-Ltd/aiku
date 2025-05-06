<?php

/*
 * author Arya Permana - Kirin
 * created on 05-05-2025-16h-24m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\Webpage\UI;

use App\Models\Web\Website;

trait WithMenuSubNavigation
{
    protected function getMenuSubNavigation(Website $website): array
    {
        return [
            [
                'isAnchor'   => true,
                'label'    => __('Menu'),
                'route'     => [
                    'name'       => 'grp.org.shops.show.web.websites.workshop.menu',
                    'parameters' => [$website->organisation->slug, $website->shop->slug, $website->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Menu')
                ]
            ],
            [
                'label'    => __('Snapshots'),
                'route'     => [
                    'name'       => 'grp.org.shops.show.web.websites.workshop.snapshots.menu',
                    'parameters' => [$website->organisation->slug, $website->shop->slug, $website->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-folder'],
                    'tooltip' => __('snapshots')
                ]
            ],
        ];
    }

}
