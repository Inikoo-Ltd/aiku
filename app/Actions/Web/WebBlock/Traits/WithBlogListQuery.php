<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

namespace App\Actions\Web\WebBlock\Traits;

use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait WithBlogListQuery
{
    protected const DEFAULT_NUMBER_OF_POSTS = 5;

    protected const MAX_NUMBER_OF_POSTS = 12;

    public function getNumberOfPosts(array $webBlock): int
    {
        $numberOfPosts = (int) Arr::get(
            $webBlock,
            'web_block.layout.data.fieldValue.number_of_posts',
            self::DEFAULT_NUMBER_OF_POSTS
        );

        return max(1, min($numberOfPosts, self::MAX_NUMBER_OF_POSTS));
    }

    /**
     * @return array<int, string>
     */
    public function getCategories(array $webBlock): array
    {
        $allowed    = WebpageSubTypeEnum::blogCategoryValues();
        $categories = Arr::get($webBlock, 'web_block.layout.data.fieldValue.categories');

        if (!is_array($categories)) {
            $categories = array_filter([$categories]);
        }

        $categories = array_values(array_intersect($categories, $allowed));

        return $categories ?: $allowed;
    }

    /**
     * @return array<int, array{id: int, title: string, image_src: ?string, image_alt: ?string, third_party_image_preview: ?string, url: ?string, published_at: ?string}>
     */
    public function getBlogList(Webpage $webpage, int $numberOfPosts, array $categories): array
    {
        $blogs = DB::table('webpages')
            ->select('id', 'title', 'published_layout', 'canonical_url', 'live_at', 'last_published_at')
            ->where('website_id', $webpage->website_id)
            ->where('type', WebpageTypeEnum::BLOG)
            ->where('state', WebpageStateEnum::LIVE)
            ->whereIn('sub_type', $categories)
            ->where('id', '!=', $webpage->id)
            ->whereNull('deleted_at')
            ->latest('live_at')
            ->limit($numberOfPosts)
            ->get();

        return $blogs->map(function ($blog) {
            $fieldValue = Arr::get(
                json_decode($blog->published_layout, true),
                'web_blocks.0.web_block.layout.data.fieldValue'
            );
            $imageData   = Arr::get($fieldValue, 'image');
            $publishedAt = Arr::get($fieldValue, 'published_date')
                ?? $blog->last_published_at
                ?? $blog->live_at;

            return [
                'id'                        => $blog->id,
                'title'                     => $blog->title,
                'image_src'                 => Arr::get($imageData, 'source'),
                'image_alt'                 => Arr::get($imageData, 'alt'),
                'third_party_image_preview' => Arr::get($fieldValue, 'third_party_image_preview'),
                'url'                       => ShowIrisWebpage::make()->getEnvironmentUrl($blog->canonical_url),
                'published_at'              => $publishedAt ? Carbon::parse($publishedAt)->format('D, jS F Y') : null,
            ];
        })->all();
    }

    public function getBlogIndexUrl(Webpage $webpage): string
    {
        return ShowIrisWebpage::make()->getEnvironmentUrl('https://www.'.$webpage->website->domain.'/blog');
    }
}
