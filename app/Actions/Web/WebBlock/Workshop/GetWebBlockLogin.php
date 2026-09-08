<?php

/*
 * Author Louis Perez
 * Created on 08-09-2026-14h-55m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Actions\Web\WebBlock\Traits\WithBlogCategoriesBlock;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockLogin
{
    use AsObject;
    use WithBlogCategoriesBlock;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        data_set($webBlock, 'web_block.layout.data.permissions', []);
        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.categories',
            $this->getBlogCategories($webpage, $webBlock)
        );
        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.blogs',
            $this->getBlogCategoriesList($webpage, $webBlock)
        );
        data_set($webBlock, 'web_block.layout.data.fieldValue.blogs_total', $this->getBlogsTotal($webpage));

        return $webBlock;
    }
}
