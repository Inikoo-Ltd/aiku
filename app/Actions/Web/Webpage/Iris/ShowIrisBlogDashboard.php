<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 14:48:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Iris;

use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowIrisBlogDashboard
{
    use AsAction;

    public function asController(ActionRequest $request): Response
    {
        return $this->handle($request);
    }


    public function handle(ActionRequest $request): Response
    {
        $website = $request->input('website');

        $blogs = [];
        foreach (
            DB::table('webpages')->where('website_id', $website->id)->select('id', 'title', 'published_layout', 'canonical_url', 'last_published_at')
                ->where('type', WebpageTypeEnum::BLOG)
                ->where('state', WebpageStateEnum::LIVE)
                ->latest('live_at')
                ->limit(100)
                ->get() as $webpageBlog
        ) {
            $publishedLayout = json_decode($webpageBlog->published_layout, true);
            $imageData       = Arr::get($publishedLayout, 'web_blocks.0.web_block.layout.data.fieldValue.image');

            $publishedAt = null;
            if ($webpageBlog->last_published_at) {
                $publishedAt = Carbon::parse($webpageBlog->last_published_at)->format('D, jS F Y');
            }

            $blogs[] = [
                'id'           => $webpageBlog->id,
                'title'        => $webpageBlog->title,
                'image_src'    => Arr::get($imageData, 'source'),
                'image_alt'    => Arr::get($imageData, 'alt'),
                'url'          => ShowIrisWebpage::make()->getEnvironmentUrl($webpageBlog->canonical_url),
                'published_at' => $publishedAt
            ];
        }


        return Inertia::render(
            'BlogDashboard',
            [
                'blogs' => $blogs,
            ]
        );
    }
}
