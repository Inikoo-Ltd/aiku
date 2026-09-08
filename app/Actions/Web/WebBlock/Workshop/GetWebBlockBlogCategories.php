<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Actions\Web\WebBlock\Traits\WithBlogCategoriesBlock;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockBlogCategories
{
    use AsObject;
    use WithBlogCategoriesBlock;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        data_set($webBlock, 'web_block.layout.data.permissions', ['edit']);
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
