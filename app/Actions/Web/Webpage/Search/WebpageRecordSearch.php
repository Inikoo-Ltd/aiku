<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Web\Webpage\Search;

use App\Enums\Web\Website\WebsiteTypeEnum;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;

class WebpageRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(Webpage $webpage): void
    {

        if ($webpage->trashed()) {

            if ($webpage->universalSearch) {
                $webpage->universalSearch()->delete();
            }
            return;
        }

        $modelData = [
            'group_id'          => $webpage->group_id,
            'website_id'        => $webpage->website_id,
            'website_slug'      => $webpage->website->slug,
            'shop_id'           => $webpage->website->shop_id,
            'shop_slug'         => $webpage->website->shop->slug,
            'organisation_id'   => $webpage->organisation_id,
            'organisation_slug' => $webpage->organisation->slug,
            'sections'          => ['web'],
            'haystack_tier_1'   => trim($webpage->code.' '.$webpage->url),
            'result'            => [
                'xx'    => $webpage,
                'route'     => match($webpage->website->type) {
                    'fulfilment' => [
                        'name'          => 'grp.org.fulfilments.show.web.webpages.show',
                        'parameters'    => [
                            $webpage->organisation->slug,
                            $webpage->shop->slug,
                            $webpage->website->slug,
                            $webpage->slug
                        ]
                    ],
                    default => [
                        'name'          => 'grp.org.shops.show.web.webpages.show',
                        'parameters'    => [
                            $webpage->organisation->slug,
                            $webpage->shop->slug,
                            $webpage->website->slug,
                            $webpage->slug
                        ]
                    ],
                },
                'description'     => [
                    'label'   => $webpage->url
                ],
                'code'         => [
                    'label' => $webpage->code,
                ],
                'icon'          => [
                    'icon' => 'fal fa-globe'
                ],
                'meta'          => [
                    [
                        'label'     => $webpage->state->labels()[$webpage->state->value],
                        'tooltip'   => __('State'),
                    ],
                    [
                        'icon' => $webpage->type->stateIcon()[$webpage->type->value],
                        'label'     => $webpage->type->labels()[$webpage->type->value],
                        'tooltip'   => __('Type'),
                    ],
                    [
                        'type' => 'number',
                        'number' => $webpage->level,
                        'label'     => __('Level'),
                    ],
                ],
            ]
        ];

        if ($webpage->website->type == WebsiteTypeEnum::FULFILMENT) {
            $modelData['fulfilment_id'] = $webpage->shop->fulfilment->id;
            $modelData['fulfilment_slug'] = $webpage->shop->fulfilment->slug;
        }

        $webpage->universalSearch()->updateOrCreate(
            [],
            $modelData
        );
    }


}
