<?php

namespace App\Actions\Web\Webpage\UI;

use App\Models\Web\Website;

trait WithHeaderSubNavigation
{
    protected function getHeaderSubNavigation(Website $website): array
    {
        return [
            [
                'isAnchor'   => true,
                'label'    => __('Header'),
                'route'     => [
                    'name'       => 'grp.org.shops.show.web.websites.workshop.header',
                    'parameters' => [$website->organisation->slug, $website->shop->slug, $website->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Header')
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
