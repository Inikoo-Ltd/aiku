<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 12:56:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Search;

use App\Enums\Web\Website\WebsiteTypeEnum;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class WebsiteRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(Website $website): void
    {

        if ($website->trashed()) {

            if ($website->universalSearch) {
                $website->universalSearch()->delete();
            }

            return;
        }

        $modelData = [
            'group_id' => $website->group_id,
            'organisation_id' => $website->organisation_id,
            'organisation_slug' => $website->organisation->slug,
            'shop_id' => $website->shop_id,
            'shop_slug' => $website->shop->slug,
            'website_id' => $website->id,
            'website_slug' => $website->slug,
            'sections' => ['web'],
            'haystack_tier_1' => trim($website->code.' '.$website->name.' '.$website->domain),
            'result' => [
                'route' => match ($website->type) {
                    'fulfilment' => [
                        'name' => 'grp.org.fulfilments.show.web.websites.show',
                        'parameters' => [
                            $website->organisation->slug,
                            $website->shop->slug,
                            $website->slug,
                        ],
                    ],
                    default => [
                        'name' => 'grp.org.shops.show.web.websites.show',
                        'parameters' => [
                            $website->organisation->slug,
                            $website->shop->slug,
                            $website->slug,
                        ],
                    ],
                },
                'description' => [
                    'label' => $website->name,
                ],
                'code' => [
                    'label' => $website->code,
                ],
                'icon' => [
                    'icon' => 'fal fa-globe',
                ],
                'meta' => [
                    [
                        'icon' => $website->state->stateIcon()[$website->state->value],
                        'label' => $website->state->labels()[$website->state->value],
                        'tooltip' => __('State'),
                    ],
                    [
                        'type' => 'date',
                        'label' => $website->created_at,
                        'tooltip' => __('Created at'),
                    ],
                    [
                        'label' => $website->domain,
                        'tooltip' => __('Domain'),
                    ],
                ],
            ],
        ];

        if ($website->type == WebsiteTypeEnum::FULFILMENT) {
            $modelData['fulfilment_id'] = $website->shop->fulfilment->id;
            $modelData['fulfilment_slug'] = $website->shop->fulfilment->slug;
        }

        $website->universalSearch()->updateOrCreate(
            [],
            $modelData
        );

    }
}
