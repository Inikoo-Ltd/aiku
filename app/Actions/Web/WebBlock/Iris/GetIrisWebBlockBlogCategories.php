<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Web\WebBlock\Traits\WithBlogCategoriesBlock;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockBlogCategories
{
    use AsObject;
    use WithBlogCategoriesBlock;

    public function handle(Webpage $webpage, array $webBlock): ?array
    {
        $categories = $this->getBlogCategories($webpage, $webBlock);
        $blogs      = $this->getBlogCategoriesList($webpage, $webBlock);

        if (empty($categories) && empty($blogs)) {
            return null;
        }

        data_set($webBlock, 'web_block.layout.data.fieldValue.categories', $categories);
        data_set($webBlock, 'web_block.layout.data.fieldValue.blogs', $blogs);
        data_set($webBlock, 'web_block.layout.data.fieldValue.blogs_total', $this->getBlogsTotal($webpage));

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
