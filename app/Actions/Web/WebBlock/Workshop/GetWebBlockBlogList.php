<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Actions\Web\WebBlock\Traits\WithBlogListQuery;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockBlogList
{
    use AsObject;
    use WithBlogListQuery;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        data_forget($webBlock, 'web_block.layout.data.permissions');
        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.blogs',
            $this->getBlogList($webpage, $webBlock)
        );
        data_set($webBlock, 'web_block.layout.data.fieldValue.blog_index_url', $this->getBlogIndexUrl($webpage));

        return $webBlock;
    }
}
