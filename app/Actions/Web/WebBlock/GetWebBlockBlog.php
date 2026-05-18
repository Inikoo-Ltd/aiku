<?php

/*
 * author Arya Permana - Kirin
 * created on 03-07-2025-11h-03m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\WebBlock;

use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockBlog
{
    use AsObject;


    public function handle(Webpage $webpage, array $webBlock): array
    {
        $permissions = ['edit'];

        $latestBlogs = [];
        foreach (
            DB::table('webpages')->where('website_id', $webpage->website_id)->select('id', 'title', 'published_layout', 'canonical_url')
                ->where('type', WebpageTypeEnum::BLOG)
                ->where('state', WebpageStateEnum::LIVE)
                ->where('id', '!=', $webpage->id)
                ->latest('live_at')
                ->limit(5)
                ->get() as $webpageBlog
        ) {
            $publishedLayout = json_decode($webpageBlog->published_layout, true);
            $imageData       = Arr::get($publishedLayout, 'web_blocks.0.web_block.layout.data.fieldValue.image');
            $latestBlogs[]   = [
                'id'        => $webpageBlog->id,
                'title'     => $webpageBlog->title,
                'image_src' => Arr::get($imageData, 'source'),
                'image_alt' => Arr::get($imageData, 'alt'),
                'url'       => ShowIrisWebpage::make()->getEnvironmentUrl($webpageBlog->canonical_url)
            ];
        }

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue.published_date', $webpage->snapshots()->latest()->first()->published_at);
        data_set($webBlock, 'web_block.layout.data.fieldValue.latest_blogs', $latestBlogs);

        return $webBlock;
    }
}
