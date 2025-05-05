<?php

/*
 * author Arya Permana - Kirin
 * created on 05-05-2025-16h-29m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\Webpage\UI;

use App\Models\Web\Website;

trait WithFooterSubNavigation
{
    protected function getFooterSubNavigation(Website $website): array
    {
        return [
            [
                'isAnchor'   => true,
                'label'    => __('Menu'),
                'route'     => [
                    'name'       => 'grp.org.shops.show.web.websites.workshop.footer',
                    'parameters' => [$website->organisation->slug, $website->shop->slug, $website->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Footer')
                ]
            ],
            [
                'label'    => __('Snapshots'),
                'route'     => [
                    // 'name'       => 'grp.org.shops.show.catalogue.departments.show.sub-departments.show.family.index',
                    // 'parameters' => [$this->organisation->slug, $this->shop->slug, $subDepartment->department->slug, $subDepartment->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-folder'],
                    'tooltip' => __('snapshots')
                ]
            ],
        ];
    }

}
