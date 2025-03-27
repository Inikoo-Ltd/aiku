<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Feb 2024 16:51:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Iris;

use App\Models\Web\Banner;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowHome
{
    use AsController;

    public function handle(ActionRequest $request, $path = null): Response
    {
        /** @var Website $website */
        $website   = $request->get('website');

        $webpage      = $website->storefront;
        if ($path && !$webpage = $website->webpages()->where('url', $path)->first()) {
            abort(404, 'Webpage not found');
        }

        $webPageLayout = $webpage->published_layout;

        $webBlocks = collect(Arr::get($webPageLayout, 'web_blocks'));
        foreach ($webBlocks as $key => $webBlock) {
            if (Arr::get($webBlock, 'type') === 'banner') {
                $fieldValue = Arr::get($webBlock, 'web_block.layout.data.fieldValue', []);
                $bannerId = Arr::get($fieldValue, 'banner_id');

                if ($banner = Banner::find($bannerId)) {
                    $fieldValue['compiled_layout'] = $banner->compiled_layout;

                    data_set($webBlock, 'web_block.layout.data.fieldValue', $fieldValue);
                }

                $webBlocks[$key] = $webBlock;
            }
        }

        $webPageLayout['web_blocks'] = $webBlocks->toArray();

        return Inertia::render(
            'Home',
            [
                /* 'head' => [
                    'title' => $webpage?->title,
                    'description' => $webpage?->description,
                    'seo' => $webpage?->data,
                ], */
                'meta' => $webpage?->seo_data,
                'blocks' => $webPageLayout ,
                'data' => $website,
            ]
        );
    }
}
