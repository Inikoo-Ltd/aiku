<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

namespace App\Actions\Web\WebBlock\Traits;

use App\Actions\Web\Webpage\Iris\ShowIrisBlogDashboard;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

trait WithBlogCategoriesBlock
{
    use WithBlogListQuery;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBlogCategories(Webpage $webpage, array $webBlock): array
    {
        return $this->applyCategoryContent(
            ShowIrisBlogDashboard::make()->getCategories($webpage->website),
            $webBlock
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBlogCategoriesList(Webpage $webpage, array $webBlock): array
    {
        if (Arr::get($webBlock, 'web_block.layout.data.fieldValue.show_list') === false) {
            return [];
        }

        return $this->getBlogList($webpage, $this->blogListBlock($webBlock));
    }

    /**
     * Live blogs of the website, so the block knows whether there is more to load than the posts it
     * was given.
     */
    public function getBlogsTotal(Webpage $webpage): int
    {
        return DB::table('webpages')
            ->where('website_id', $webpage->website_id)
            ->where('type', WebpageTypeEnum::BLOG)
            ->where('state', WebpageStateEnum::LIVE)
            ->whereNull('deleted_at')
            ->whereIn(DB::raw(WebpageSubTypeEnum::blogCategorySqlExpression()), WebpageSubTypeEnum::blogCategoryValues())
            ->count();
    }

    /**
     * The field value of this block keeps the blog categories to render, while the blog list query reads
     * that same key as the sub types to filter the posts by, so it is dropped to list posts of every category.
     */
    public function blogListBlock(array $webBlock): array
    {
        data_forget($webBlock, 'web_block.layout.data.fieldValue.categories');

        return $webBlock;
    }

    /**
     * Descriptions are written in the workshop editor, so an emptied one still arrives as markup.
     */
    public function hasEditorContent(?string $value): bool
    {
        return trim(strip_tags((string) $value)) !== '';
    }

    /**
     * The label, description and image of each blog sub type are edited in the workshop and live in the
     * block field value, so they travel with the webpage snapshot and every shop keeps its own wording
     * and artwork. What is not filled in falls back to the values derived from the website blogs.
     *
     * @param  array<int, array<string, mixed>>  $categories
     * @return array<int, array<string, mixed>>
     */
    public function applyCategoryContent(array $categories, array $webBlock): array
    {
        $categoryContent = Arr::get($webBlock, 'web_block.layout.data.fieldValue.category_content') ?? [];

        return array_map(function (array $category) use ($categoryContent): array {
            $content = Arr::get($categoryContent, Arr::get($category, 'value')) ?? [];

            $label       = trim((string) Arr::get($content, 'label'));
            $description = trim((string) Arr::get($content, 'description'));
            $image       = Arr::get($content, 'image');

            if ($label !== '') {
                $category['custom_label'] = $label;
            }

            if ($this->hasEditorContent($description)) {
                $category['description'] = $description;
            }

            if ($image) {
                $category['image_src']                 = $image;
                $category['image_alt']                 = Arr::get($content, 'image_alt')
                    ?: ($label !== '' ? $label : Arr::get($category, 'label'));
                $category['third_party_image_preview'] = null;
            }

            return $category;
        }, $categories);
    }
}
