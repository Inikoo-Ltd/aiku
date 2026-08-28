<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 14:48:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Iris;

use App\Actions\Iris\Blog\IndexIrisBlogs;
use App\Actions\Web\Webpage\Traits\WithIrisBlogBreadcrumbs;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Http\Resources\Web\BlogsIrisResource;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowIrisBlogDashboard
{
    use AsAction;
    use WithIrisBlogBreadcrumbs;

    private const COVER_LOOKUP_LIMIT = 10;

    public function handle(Website $website): LengthAwarePaginator
    {
        return IndexIrisBlogs::make()->handle($website, IndexIrisBlogs::PREFIX, WebpageSubTypeEnum::blogCategories());
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        /** @var Website $website */
        $website = $request->input('website');

        return $this->handle($website);
    }

    public function htmlResponse(LengthAwarePaginator $blogs, ActionRequest $request): Response
    {
        /** @var Website $website */
        $website = $request->input('website');

        return Inertia::render(
            'BlogDashboard',
            [
                'breadcrumbs' => $this->getIrisBlogDashboardBreadcrumbs(),
                'subtitle'   => __("Three ways to explore — choose what you're here for."),
                'categories' => $this->getCategories($website),
                'newsletter' => [
                    'eyebrow'     => __('Stay in the loop'),
                    'title'       => __('Get the newsletter'),
                    'description' => __('Join thousands of readers and get travel stories, guides, and tips delivered to your inbox.'),
                ],
                'explore'    => [
                    'eyebrow'     => __('New here?'),
                    'title'       => __('Start exploring'),
                    'description' => __('Dive into the latest stories, guides, and tips across all categories.'),
                    'label'       => __('Browse All Blogs'),
                ],
                'data'       => BlogsIrisResource::collection($blogs),
            ]
        )->table(IndexIrisBlogs::make()->tableStructure($website, IndexIrisBlogs::PREFIX, WebpageSubTypeEnum::blogCategories()));
    }

    /**
     * @return array<int, array{value: string, label: string, description: string, url: string, icon: string, fallback_image: string, count: int, image_src: mixed, third_party_image_preview: mixed, image_alt: mixed}>
     */
    public function getCategories(Website $website): array
    {
        $blogCategory = WebpageSubTypeEnum::blogCategorySqlExpression();

        $counts = Webpage::where('webpages.website_id', $website->id)
            ->where('webpages.type', WebpageTypeEnum::BLOG)
            ->where('webpages.state', WebpageStateEnum::LIVE)
            ->whereIn(DB::raw($blogCategory), WebpageSubTypeEnum::blogCategoryValues())
            ->groupBy(DB::raw($blogCategory))
            ->selectRaw($blogCategory.' as blog_category, count(*) as total')
            ->pluck('total', 'blog_category');

        $categories = [];
        foreach ($this->getCategoryPresentation() as $value => $presentation) {
            $categories[] = array_merge(
                [
                    'value'          => $value,
                    'url'            => $presentation['url'],
                    'label'          => $presentation['label'],
                    'description'    => $presentation['description'],
                    'icon'           => $presentation['icon'],
                    'fallback_image' => $presentation['fallback_image'],
                    'count'          => (int) Arr::get($counts, $value, 0),
                ],
                $this->getCategoryCover($website, $value)
            );
        }

        return $categories;
    }

    /**
     * @return array<string, array{label: string, description: string, url: string, icon: string, fallback_image: string}>
     */
    private function getCategoryPresentation(): array
    {
        return [
            WebpageSubTypeEnum::NEWSLETTERS->value    => [
                'label'          => __("David's Travel Blog (Newsletter)"),
                'description'    => __('Stories from the road, lessons learned, and places that leave a mark. Subscribe to get new travel stories and reflections straight to your inbox.'),
                'url'            => WebpageSubTypeEnum::NEWSLETTERS->blogCategoryUrl(),
                'icon'           => 'fal fa-plane-departure',
                'fallback_image' => '/art/blog/newsletters.webp',
            ],
            WebpageSubTypeEnum::PRODUCT_GUIDES->value => [
                'label'          => __('Product Guides'),
                'description'    => __('In-depth guides, reviews, and recommendations to help you choose the right tools and products with confidence.'),
                'url'            => WebpageSubTypeEnum::PRODUCT_GUIDES->blogCategoryUrl(),
                'icon'           => 'fal fa-book-open',
                'fallback_image' => '/art/blog/product-guides.webp',
            ],
            WebpageSubTypeEnum::BUSINESS_TIPS->value  => [
                'label'          => __('Business Tips'),
                'description'    => __('Practical strategies, frameworks, and insights to help you grow your business and work smarter every day.'),
                'url'            => WebpageSubTypeEnum::BUSINESS_TIPS->blogCategoryUrl(),
                'icon'           => 'fal fa-chart-bar',
                'fallback_image' => '/art/blog/business-tips.webp',
            ],
        ];
    }

    /**
     * @return array{image_src: mixed, third_party_image_preview: mixed, image_alt: mixed}
     */
    private function getCategoryCover(Website $website, string $subType): array
    {
        $recentLayouts = Webpage::where('webpages.website_id', $website->id)
            ->where('webpages.type', WebpageTypeEnum::BLOG)
            ->where('webpages.state', WebpageStateEnum::LIVE)
            ->whereIn(DB::raw(WebpageSubTypeEnum::blogCategorySqlExpression()), [$subType])
            ->orderByDesc('webpages.live_at')
            ->limit(self::COVER_LOOKUP_LIMIT)
            ->pluck('webpages.published_layout');

        foreach ($recentLayouts as $layout) {
            $fieldValue = Arr::get($layout, 'web_blocks.0.web_block.layout.data.fieldValue');

            $cover = [
                'image_src'                 => Arr::get($fieldValue, 'image.source'),
                'third_party_image_preview' => Arr::get($fieldValue, 'third_party_image_preview'),
                'image_alt'                 => Arr::get($fieldValue, 'image.alt'),
            ];

            if ($cover['image_src'] || $cover['third_party_image_preview']) {
                return $cover;
            }
        }

        return [
            'image_src'                 => null,
            'third_party_image_preview' => null,
            'image_alt'                 => null,
        ];
    }
}
