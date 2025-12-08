<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Web\Banner\Search;

use App\Models\Web\Banner;
use Lorisleiva\Actions\Concerns\AsAction;

class BannerRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(Banner $banner): void
    {

        if ($banner->trashed()) {

            if ($banner->universalSearch) {
                $banner->universalSearch()->delete();
            }

            return;
        }

        $banner->universalSearch()->updateOrCreate(
            [],
            [
                'group_id' => $banner->group_id,
                'model_id' => $banner->id,
                'model_type' => class_basename(Banner::class),
                'organisation_id' => $banner->organisation_id,
                'organisation_slug' => $banner->organisation->slug,
                'shop_id' => $banner->shop_id,
                'shop_slug' => $banner->shop->slug,
                'website_id' => $banner->website_id,
                'website_slug' => $banner->website->slug,
                'sections' => ['web'],
                'haystack_tier_1' => trim($banner->name),
                'result' => [
                    'route' => match ($banner->website->type) {
                        'fulfilment' => [
                            'name' => 'grp.org.fulfilments.show.web.banners.show',
                            'parameters' => [
                                $banner->organisation->slug,
                                $banner->shop->slug,
                                $banner->website->slug,
                                $banner->slug,
                            ],
                        ],
                        default => [
                            'name' => 'grp.org.shops.show.web.banners.show',
                            'parameters' => [
                                $banner->organisation->slug,
                                $banner->shop->slug,
                                $banner->website->slug,
                                $banner->slug,
                            ],
                        ],
                    },
                    'description' => [
                        'label' => $banner->name,
                    ],
                    'code' => [
                        'label' => $banner->name,
                    ],
                    'icon' => [
                        'icon' => 'fal fa-sign',
                    ],
                    'meta' => [
                        [
                            'icon' => 'fal fa-globe',
                            'label' => $banner->website->name,
                            'tooltip' => __('State'),
                        ],
                        [
                            'type' => 'date',
                            'label' => $banner->created_at,
                            'tooltip' => __('Created at'),
                        ],
                        [
                            'label' => $banner->domain,
                            'tooltip' => __('Domain'),
                        ],
                    ],
                ],
            ]
        );
    }
}
