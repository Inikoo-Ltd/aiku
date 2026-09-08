<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Traits;

use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use Illuminate\Support\Arr;

trait WithIrisBlogBreadcrumbs
{
    /**
     * @return array<int, array{type: string, simple: array<string, string>}>
     */
    protected function getIrisBlogDashboardBreadcrumbs(?WebpageSubTypeEnum $blogCategory = null): array
    {
        $breadcrumbs = [
            [
                'type'   => 'simple',
                'simple' => [
                    'icon' => 'fal fa-home',
                    'url'  => '/'
                ]
            ],
            [
                'type'   => 'simple',
                'simple' => [
                    'short_label' => __('blog'),
                    'label'       => __('Blog'),
                    'url'         => '/blog'
                ]
            ],
        ];

        if ($blogCategory && in_array($blogCategory, WebpageSubTypeEnum::blogCategories(), true)) {
            $label = Arr::get(WebpageSubTypeEnum::labels(), $blogCategory->value, $blogCategory->value);

            $breadcrumbs[] = [
                'type'   => 'simple',
                'simple' => array_filter([
                    'short_label' => $label,
                    'label'       => $label,
                    'url'         => $blogCategory->blogCategoryUrl(),
                ]),
            ];
        }

        return $breadcrumbs;
    }
}
