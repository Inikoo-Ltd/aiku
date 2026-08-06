<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Web\WebBlock\Traits\WithBlogListQuery;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockBlogList
{
    use AsObject;
    use WithBlogListQuery;

    public function handle(Webpage $webpage, array $webBlock): ?array
    {
        $blogs = $this->getBlogList($webpage, $this->getNumberOfPosts($webBlock));

        if (empty($blogs)) {
            return null;
        }

        data_set($webBlock, 'web_block.layout.data.fieldValue.blogs', $blogs);
        data_set($webBlock, 'web_block.layout.data.fieldValue.blog_index_url', $this->getBlogIndexUrl($webpage));

        return [
            'type'      => $webBlock['type'],
            'structure' => Arr::get(
                $webBlock,
                'web_block.layout.data.fieldValue',
                []
            ),
        ];
    }
}
