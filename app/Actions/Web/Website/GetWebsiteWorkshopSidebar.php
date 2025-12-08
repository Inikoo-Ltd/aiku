<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 12 Sep 2025 15:38:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Catalogue\ProductCategory\Json\GetIrisProductCategoryNavigation;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopSidebar
{
    use AsObject;

    public function handle(Website $website): array
    {
        $sidebarDummy = [
            'data' => [
                'fieldValue' => [
                    'navigation' => array_values([]),
                    'navigation_bottom' => array_values([]),
                ],
            ],
            'status' => true,
        ];

        $productCategories = [
            'product_categories' => GetIrisProductCategoryNavigation::run($website),
        ];

        if (! Arr::get($website->unpublishedSidebarSnapshot, 'layout.sidebar')) {

            $sidebar = Arr::get($website->published_layout, 'sidebar', $sidebarDummy);
            $sidebar['data']['fieldValue'] = array_merge(
                $sidebar['data']['fieldValue'],
                $productCategories
            );

            return [
                'sidebar' => $sidebar,
            ];
        }

        $sidebar = Arr::get($website->unpublishedSidebarSnapshot, 'layout.sidebar', $sidebarDummy);
        $sidebar['data']['fieldValue'] = array_merge(
            $sidebar['data']['fieldValue'],
            $productCategories
        );

        return [
            'sidebar' => $sidebar,
        ];
    }
}
