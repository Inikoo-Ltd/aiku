<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\ProductCategory\CheckFamilyWebpage;
use App\Actions\Traits\Authorisations\WithShopPpcPermissions;
use App\Enums\UI\Catalogue\FamilyTabsEnum;
use App\Enums\Web\Crawl\CrawlIssueSeverityEnum;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Enums\Web\Seo\SeoContentSuggestionStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\CrawlIssue;
use App\Models\Web\CrawlPage;
use App\Models\Web\SeoContentSuggestion;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetFamilyPageScore
{
    use AsObject;
    use WithShopPpcPermissions;

    private const array CRAWL_CHECKS = ['robots_txt', 'crawlable_content', 'h1', 'canonical_url', 'structured_data', 'sitemap', 'internal_links', 'broken_links', 'page_speed', 'alt_image'];

    /**
     * How far the family webpage is through its SEO workflow and which of its checks pass. Each
     * check sits in one area for display and counts toward one of four scores out of 10 (AI
     * discoverability, SEO, product data, content quality). Checks read from the site audit are null
     * until an audit or a page check (CheckFamilyWebpage) has crawled the page; robots.txt, internal
     * links, crawlable content and structured data come only from the page check, which also asks
     * every link of the page live. Meta title and description pass once filled in; a length
     * the crawl found outside the limits is shown in their note without failing them.
     *
     * @return null|array{steps: array<int, array{key: string, label: string, owner: string, is_done: bool}>, score: int, impact: array{level: string, label: string, note: string}, passed: int, total: int, last_crawl_at: ?string, links_check: array{webpage_id: int, is_checking: bool}, findings: ?array<int, array{type: string, label: string, description: string, is_error: bool}>, scores: array<int, array{key: string, label: string, score: int, passed: int, total: int}>, areas: array<int, array{key: string, label: string, checks: array<int, array{key: string, label: string, score: string, is_passed: ?bool, note: ?string, is_crawl: bool}>}>, crawl_route: ?array, setting_route: array, issues_route: array}
     */
    public function handle(ProductCategory $family): ?array
    {
        $webpage = $family->webpage;

        if (!$webpage) {
            return null;
        }

        $auditPage  = $this->latestCrawlPage($webpage);
        $crawlPage  = CheckFamilyWebpage::checkedPage($webpage, $auditPage);
        $issueTypes = $crawlPage?->issues->map(fn (CrawlIssue $issue) => $issue->type);
        $isClearOf  = fn (array $types): ?bool => $issueTypes ? !$issueTypes->contains(fn (CrawlIssueTypeEnum $type) => in_array($type, $types, true)) : null;
        $visibility = $webpage->searchEngineVisibility();
        $isLive     = $webpage->state === WebpageStateEnum::LIVE;

        $check              = CheckFamilyWebpage::pageCheck($webpage);
        $checkedBrokenLinks = CheckFamilyWebpage::checkedBrokenLinks($webpage, $auditPage);
        $titleIssues        = CheckFamilyWebpage::TITLE_ISSUES;
        $descriptionIssues  = CheckFamilyWebpage::META_DESCRIPTION_ISSUES;

        $areas = collect([
            'content'     => [__('Content'), [
                'meta_title'        => [__('Meta title'), 'seo', $this->hasText($webpage->title), CheckFamilyWebpage::issueNote($crawlPage, $titleIssues, $webpage)],
                'meta_description'  => [__('Meta description'), 'seo', $this->hasText($webpage->description), CheckFamilyWebpage::issueNote($crawlPage, $descriptionIssues, $webpage)],
                'top_description'   => [__('Top description'), 'content_quality', $this->hasText($family->description)],
                'extra_description' => [__('Extra description'), 'content_quality', $this->hasText($family->description_extra)],
                'faq'               => [__('FAQ'), 'ai_discoverability', collect($family->faq)->contains(fn ($faq) => is_array($faq) && $this->hasText($faq['question'] ?? null))],
                'price'             => [__('Price'), 'product_data', Product::where('family_id', $family->id)->where('is_for_sale', true)->where('price', '>', 0)->exists()],
                'crawlable_content' => [__('Crawlable content'), 'ai_discoverability', $check['is_content_in_html'] ?? null],
            ]],
            'structure'   => [__('Structure'), [
                'h1'               => [__('H1'), 'seo', $isClearOf([CrawlIssueTypeEnum::MISSING_H1, CrawlIssueTypeEnum::MULTIPLE_H1])],
                'url'              => [__('URL'), 'seo', (bool)preg_match('/^[a-z0-9]+(?:[-\/][a-z0-9]+)*$/', $webpage->url)],
                'breadcrumb_label' => [__('Breadcrumb label'), 'seo', $this->hasText($webpage->breadcrumb_label)],
                'canonical_url'    => [__('Canonical URL'), 'seo', $this->hasText($webpage->canonical_url) && $isClearOf([CrawlIssueTypeEnum::MISSING_CANONICAL, CrawlIssueTypeEnum::CANONICAL_TO_BROKEN]) !== false],
                'structured_data'  => [__('Structured data'), 'ai_discoverability', $check['structured_data']['is_valid'] ?? null],
                'sitemap'          => [__('Sitemap'), 'seo', $isClearOf([CrawlIssueTypeEnum::NOT_IN_SITEMAP, CrawlIssueTypeEnum::NOINDEX_IN_SITEMAP])],
            ]],
            'links'       => [__('Links & images'), [
                'alt_image'      => [__('Alt image'), 'seo', $isClearOf([CrawlIssueTypeEnum::IMAGES_WITHOUT_ALT])],
                'broken_links'   => [__('Broken links'), 'seo', $checkedBrokenLinks !== null ? $checkedBrokenLinks === [] : ($auditPage ? !$this->linksToBrokenPages($auditPage) : null)],
                'internal_links' => [__('Internal links'), 'seo', isset($check['internal_links']) ? $check['internal_links'] > 0 : null],
                'image'          => [__('Image'), 'product_data', (bool)$family->image_id],
            ]],
            'indexing'    => [__('Indexing'), [
                'index_page'  => [__('Index page'), 'seo', $visibility['index_page']],
                'follow_link' => [__('Follow link'), 'seo', $visibility['follow_link']],
                'page_status' => [__('Page status'), 'seo', $isLive],
                'robots_txt'  => [__('robots.txt'), 'ai_discoverability', $check['is_allowed_by_robots_txt'] ?? null],
            ]],
            'performance' => [__('Performance'), [
                'page_speed' => [__('Page speed'), 'seo', $isClearOf([CrawlIssueTypeEnum::SLOW_RESPONSE])],
            ]],
        ])->map(fn (array $area, string $key) => [
            'key'    => $key,
            'label'  => $area[0],
            'checks' => collect($area[1])->map(fn (array $check, string $checkKey) => [
                'key'       => $checkKey,
                'label'     => $check[0],
                'score'     => $check[1],
                'is_passed' => $check[2],
                'note'      => $check[3] ?? null,
                'is_crawl'  => in_array($checkKey, self::CRAWL_CHECKS, true),
            ])->values()->all(),
        ])->values();

        $checks = $areas->flatMap(fn (array $area) => $area['checks']);
        $passed = $checks->where('is_passed', true)->count();
        $score  = (int)round($passed * 100 / $checks->count());

        $scores = collect([
            'ai_discoverability' => __('AI Discoverability'),
            'seo'                => __('SEO'),
            'product_data'       => __('Product data'),
            'content_quality'    => __('Content quality'),
        ])->map(function (string $label, string $key) use ($checks) {
            $scoreChecks = $checks->where('score', $key);
            $scorePassed = $scoreChecks->where('is_passed', true)->count();

            return [
                'key'    => $key,
                'label'  => $label,
                'score'  => (int)round($scorePassed * 10 / $scoreChecks->count()),
                'passed' => $scorePassed,
                'total'  => $scoreChecks->count(),
            ];
        })->values();

        $suggestionStates = SeoContentSuggestion::where('webpage_id', $webpage->id)->distinct()->pluck('state');
        $isReviewed       = $suggestionStates->isNotEmpty();
        $shop             = $family->shop;
        $user             = request()->user();

        return [
            'steps'         => $this->steps(
                isCreated: true,
                isPublished: $isLive,
                isReviewed: $isReviewed,
                isImplemented: $isReviewed && !$suggestionStates->contains(fn (SeoContentSuggestionStateEnum $state) => in_array($state, [SeoContentSuggestionStateEnum::PENDING, SeoContentSuggestionStateEnum::IMPLEMENTED], true))
            ),
            'score'         => $score,
            'impact'        => $this->impact($score),
            'passed'        => $passed,
            'total'         => $checks->count(),
            'last_crawl_at' => $crawlPage?->crawl->end_at?->toIso8601String(),
            'links_check'   => CheckFamilyWebpage::linksCheck($webpage),
            'scores'        => $scores->all(),
            'areas'         => $areas->all(),
            'findings'      => $crawlPage?->issues->map(fn (CrawlIssue $issue) => [
                'type'        => $issue->type->value,
                'label'       => CrawlIssueTypeEnum::labels()[$issue->type->value],
                'description' => CrawlIssueTypeEnum::descriptions()[$issue->type->value],
                'is_error'    => $issue->type->severity() === CrawlIssueSeverityEnum::ERROR,
            ])->values()->all(),
            'crawl_route'   => $user && $this->canEditSeo($user, $shop) ? [
                'name'       => 'grp.models.family.webpage_check.store',
                'parameters' => ['family' => $family->id],
                'method'     => 'post',
            ] : null,
            'setting_route' => $this->editPageRoute($family, 'page_setting'),
            'issues_route'  => $this->editPageRoute($family, 'issues'),
        ];
    }

    private function editPageRoute(ProductCategory $family, string $section): array
    {
        return [
            'name'       => 'grp.org.shops.show.catalogue.families.show',
            'parameters' => [
                'organisation' => $family->organisation->slug,
                'shop'         => $family->shop->slug,
                'family'       => $family->slug,
                'tab'          => FamilyTabsEnum::EDIT_PAGE->value,
                'section'      => $section,
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, owner: string, is_done: bool}>
     */
    public function steps(bool $isCreated, bool $isPublished, bool $isReviewed, bool $isImplemented): array
    {
        return [
            ['key' => 'created', 'label' => __('Page created'), 'owner' => __('Webmaster'), 'is_done' => $isCreated],
            ['key' => 'published', 'label' => __('Published'), 'owner' => __('Webmaster'), 'is_done' => $isPublished],
            ['key' => 'seo_review', 'label' => __('SEO review & suggestions'), 'owner' => __('SEO team'), 'is_done' => $isReviewed],
            ['key' => 'implemented', 'label' => __('Suggestions implemented'), 'owner' => __('Webmaster'), 'is_done' => $isImplemented],
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

    private function latestCrawlPage(Webpage $webpage): ?CrawlPage
    {
        return CrawlPage::where('webpage_id', $webpage->id)
            ->whereHas('crawl', fn ($query) => $query->where('type', CrawlTypeEnum::AUDIT)->where('state', CrawlStateEnum::FINISH))
            ->with(['crawl', 'issues'])
            ->orderByDesc('crawl_id')
            ->orderByRaw('status_code = 200 desc nulls last')
            ->first();
    }

    /**
     * ponytail: reads linked_from, which the audit caps at 10 linking pages per broken URL, so a
     * broken page linked from many others may not name this one. Store the outlinks per page if that matters.
     */
    private function linksToBrokenPages(CrawlPage $crawlPage): bool
    {
        return CrawlIssue::where('crawl_id', $crawlPage->crawl_id)
            ->whereIn('type', [CrawlIssueTypeEnum::HTTP_4XX, CrawlIssueTypeEnum::HTTP_5XX])
            ->whereJsonContains('details->linked_from', $crawlPage->url)
            ->exists();
    }

    private function hasText(?string $value): bool
    {
        return trim(html_entity_decode(strip_tags($value ?? ''))) !== '';
    }
}
