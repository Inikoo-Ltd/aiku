<?php

/*
 * author Arya Permana - Kirin
 * created on 03-07-2025-11h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\WebBlock;

use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockBlog
{
    use AsObject;


    public function handle(Webpage $webpage, array $webBlock): array
    {
        $permissions = ['edit'];
        $latestBlogs = DB::table('webpages')
            ->where('website_id', $webpage->website_id)
            ->where('type', WebpageTypeEnum::BLOG->value)
            ->where('state', WebpageStateEnum::LIVE->value)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue.published_date', $webpage->snapshots()->latest()->first()->published_at);
        data_set($webBlock, 'web_block.layout.data.fieldValue.latest_blogs', $latestBlogs);

        return $webBlock;
    }
}
