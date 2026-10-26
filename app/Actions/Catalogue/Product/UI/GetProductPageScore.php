<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Created: Mon, 26 Oct 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\Product\CheckProductWebpage;
use App\Enums\Web\ModelHasContent\ModelHasContentTypeEnum;
use App\Models\Catalogue\Product;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The checks of a shop product webpage scored in the four scores Tomas asked for (AI
 * discoverability, SEO / technical, product data accuracy and content quality) and grouped into
 * the areas the webmaster fixes them in, the same way as GetFamilyPageScore. Checks that need a
 * crawl are null until the page is crawled: robots.txt, internal links and crawlable content come
 * only from "Crawl page" (CheckProductWebpage).
 */
class GetProductPageScore
{
    use AsObject;

    /**
     * Checks fixed outside the product setting: in the page template, the website or its links.
     */
    private const array CRAWL_CHECKS = ['h1', 'canonical_url', 'sitemap', 'alt_image', 'broken_links', 'internal_links', 'page_speed', 'robots_txt', 'crawlable_content'];

    /**
     * @param array{checks: array<string, array{label: string, is_passed: ?bool}>}|null $audit what GetProductWebpageAudit read of the webpage
     *
     * @return null|array{score: int, passed: int, total: int, impact: array{level: string, label: string, note: string}, scores: array<int, array{key: string, label: string, short: string, score: ?int, passed: int, total: int}>, areas: array<int, array{key: string, label: string, checks: array<int, array{key: string, label: string, category: string, is_passed: ?bool, is_crawl: bool}>}>}
     */
    public function handle(Product $product, ?array $audit): ?array
    {
        $webpage = $product->webpage;

        if (!$webpage) {
            return null;
        }

        $auditChecks  = collect($audit['checks'] ?? [])->map(fn (array $check) => $check['is_passed']);
        $pageCheck    = CheckProductWebpage::cached($webpage);
        $webpageItems = GetProductChecklist::make()->webpageItems();
        $isSet        = fn (string $key): bool => ($webpageItems[$key]['check'])($webpage);

        $results = [
            'meta_title'        => $isSet('meta_title'),
            'meta_description'  => $isSet('meta_description'),
            'top_description'   => $this->hasText($product->description),
            'extra_description' => $this->hasText($product->description_extra),
            'faq'               => $product->contents()->where('type', ModelHasContentTypeEnum::FAQ)->exists(),
            'price'             => $product->price > 0,
            'h1'                => $auditChecks->get('h1'),
            'url'               => $isSet('url'),
            'breadcrumb_label'  => $isSet('breadcrumb_label'),
            'canonical_url'     => $auditChecks->get('canonical_url'),
            'structured_data'   => $auditChecks->get('structured_data'),
            'sitemap'           => $auditChecks->get('sitemap'),
            'alt_image'         => $auditChecks->get('alt_image'),
            'broken_links'      => $auditChecks->get('broken_links'),
            'internal_links'    => isset($pageCheck['internal_links']) ? $pageCheck['internal_links'] > 0 : null,
            'image'             => (bool)$product->image_id,
            'index_page'        => $isSet('index_page'),
            'follow_link'       => $isSet('follow_link'),
            'page_status'       => $isSet('page_status'),
            'robots_txt'        => $pageCheck['is_allowed_by_robots_txt'] ?? null,
            'crawlable_content' => $pageCheck['is_content_in_html'] ?? null,
            'page_speed'        => $auditChecks->get('page_speed'),
        ];

        $checks = collect($this->checks())->map(fn (array $check, string $key) => [
            'key'       => $key,
            'label'     => $check[0],
            'category'  => $check[1],
            'area'      => $check[2],
            'is_passed' => $results[$key],
            'is_crawl'  => in_array($key, self::CRAWL_CHECKS, true),
        ]);

        $passed = $checks->where('is_passed', true)->count();
        $score  = (int)round($passed * 100 / $checks->count());

        return [
            'score'  => $score,
            'passed' => $passed,
            'total'  => $checks->count(),
            'impact' => $this->impact($score),
            'scores' => collect($this->categories())->map(function (array $category, string $key) use ($checks) {
                $categoryChecks = $checks->where('category', $key);
                $categoryPassed = $categoryChecks->where('is_passed', true)->count();

                return [
                    'key'    => $key,
                    'label'  => $category[0],
                    'short'  => $category[1],
                    'score'  => $categoryChecks->contains(fn (array $check) => $check['is_passed'] !== null) ? (int)round($categoryPassed * 10 / $categoryChecks->count()) : null,
                    'passed' => $categoryPassed,
                    'total'  => $categoryChecks->count(),
                ];
            })->values()->all(),
            'areas'  => collect($this->areas())->map(fn (string $label, string $key) => [
                'key'    => $key,
                'label'  => $label,
                'checks' => $this->withoutArea($checks->where('area', $key)),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}> label, score category and area of each check
     */
    private function checks(): array
    {
        return [
            'meta_title'        => [__('Meta title'), 'seo', 'content'],
            'meta_description'  => [__('Meta description'), 'seo', 'content'],
            'top_description'   => [__('Top description'), 'content_quality', 'content'],
            'extra_description' => [__('Extra description'), 'content_quality', 'content'],
            'faq'               => [__('FAQ'), 'ai_discoverability', 'content'],
            'price'             => [__('Price'), 'product_data', 'content'],
            'crawlable_content' => [__('Crawlable content'), 'ai_discoverability', 'content'],
            'h1'              => [__('H1'), 'seo', 'structure'],
            'url'               => [__('URL'), 'seo', 'structure'],
            'breadcrumb_label'  => [__('Breadcrumb label'), 'seo', 'structure'],
            'canonical_url'     => [__('Canonical URL'), 'seo', 'structure'],
            'structured_data'   => [__('Structured data'), 'ai_discoverability', 'structure'],
            'sitemap'           => [__('Sitemap'), 'seo', 'structure'],
            'alt_image'         => [__('Alt image'), 'seo', 'links'],
            'broken_links'      => [__('Broken links'), 'seo', 'links'],
            'internal_links'    => [__('Internal links'), 'seo', 'links'],
            'image'             => [__('Image'), 'product_data', 'links'],
            'index_page'        => [__('Index page'), 'seo', 'indexing'],
            'follow_link'       => [__('Follow link'), 'seo', 'indexing'],
            'page_status'       => [__('Page status'), 'seo', 'indexing'],
            'robots_txt'        => [__('robots.txt'), 'ai_discoverability', 'indexing'],
            'page_speed'        => [__('Page speed'), 'seo', 'performance'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}> label and short tag of each score
     */
    private function categories(): array
    {
        return [
            'ai_discoverability' => [__('AI Discoverability'), 'AI'],
            'seo'                => [__('SEO / Technical'), 'SEO'],
            'product_data'       => [__('Product Data Accuracy'), 'PD'],
            'content_quality'    => [__('Content Quality'), 'CQ'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function areas(): array
    {
        return [
            'content'     => __('Content'),
            'structure'   => __('Structure'),
            'links'       => __('Links & images'),
            'indexing'    => __('Indexing'),
            'performance' => __('Performance'),
        ];
    }

    /**
     * @return array{level: string, label: string, note: string}
     */
    private function impact(int $score): array
    {
        return match (true) {
            $score < 60 => ['level' => 'high', 'label' => __('High'), 'note' => __('Fix first')],
            $score < 90 => ['level' => 'medium', 'label' => __('Medium'), 'note' => __('Fix soon')],
            default     => ['level' => 'low', 'label' => __('Low'), 'note' => __('Fine-tune')],
        };
    }

    private function withoutArea(Collection $checks): array
    {
        return $checks->map(fn (array $check) => collect($check)->except('area')->all())->values()->all();
    }

    private function hasText(?string $value): bool
    {
        return trim(html_entity_decode(strip_tags($value ?? ''))) !== '';
    }
}
