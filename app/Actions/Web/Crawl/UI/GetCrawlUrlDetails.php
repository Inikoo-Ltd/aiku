<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl\UI;

use App\Actions\OrgAction;
use App\Actions\Web\Crawl\EnrichCrawlPages;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Crawl\CrawlLinkKindEnum;
use App\Enums\Web\Crawl\CrawlLinkPositionEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\Web\Crawl;
use App\Models\Web\CrawlPage;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\Catalogue\FamilyTabsEnum;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * Everything the crawl knows about one URL, for the panel under the site crawler table: its fields,
 * its issues, the links pointing to it, the links and images on it, and its response headers.
 */
class GetCrawlUrlDetails extends OrgAction
{
    use WithSiteCrawlerAuthorisation;

    private Crawl $crawl;

    public const int LINKS_SHOWN = 1000;

    public function authorize(ActionRequest $request): bool
    {
        return $this->canViewCrawlsOf($request->user(), $this->crawl->website);
    }

    public function handle(Crawl $crawl, string $urlHash): array
    {
        $page = CrawlPage::where('crawl_id', $crawl->id)->where('url_hash', $urlHash)->first();

        if ($page) {
            return [
                'url'      => $page->url,
                'type'     => 'page',
                'details'  => $this->pageDetails($page),
                'serp'     => [
                    'title'            => $page->title,
                    'url'              => $page->url,
                    'meta_description' => $page->meta_description,
                ],
                'issues'   => $this->issues($page),
                'inlinks'  => $this->inlinks($crawl, $urlHash),
                'outlinks' => $this->outlinks($crawl, $urlHash, false),
                'images'   => $this->outlinks($crawl, $urlHash, true),
                'headers'  => $page->facts['headers'] ?? [],
            ];
        }

        $resource = DB::table('crawl_resources')->where('crawl_id', $crawl->id)->where('url_hash', $urlHash)->first();

        abort_unless($resource, 404);

        return [
            'url'      => $resource->url,
            'type'     => $resource->kind,
            'details'  => [
                ['label' => __('Address'), 'value' => $resource->url],
                ['label' => __('Status code'), 'value' => $resource->status_code],
                ['label' => __('Content type'), 'value' => $resource->content_type],
                ['label' => __('Size (bytes)'), 'value' => $resource->bytes],
                ['label' => __('Error'), 'value' => $resource->fetch_error],
            ],
            'serp'     => null,
            'issues'   => [],
            'inlinks'  => $this->inlinks($crawl, $urlHash),
            'outlinks' => ['rows' => [], 'total' => 0],
            'images'   => ['rows' => [], 'total' => 0],
            'headers'  => [],
        ];
    }

    /**
     * @return array<int, array{label: string, value: mixed, link?: string}>
     */
    private function pageDetails(CrawlPage $page): array
    {
        $facts   = $page->facts ?? [];
        $webpage = $page->webpage_id ? Webpage::with(['website', 'shop', 'organisation', 'model'])->find($page->webpage_id) : null;

        return array_values(array_filter([
            ['label' => __('Address'), 'value' => $page->url],
            ['label' => __('Status code'), 'value' => $page->status_code],
            ['label' => __('Content type'), 'value' => $page->content_type],
            ['label' => __('Indexability'), 'value' => $page->is_indexable ? __('Indexable') : __('Non-indexable')],
            ['label' => __('Redirect URL'), 'value' => $page->redirect_to],
            ['label' => __('Title'), 'value' => $page->title],
            ['label' => __('Title length'), 'value' => $page->title === null ? null : mb_strlen($page->title)],
            ['label' => __('Meta description'), 'value' => $page->meta_description],
            ['label' => __('Meta description length'), 'value' => $page->meta_description === null ? null : mb_strlen($page->meta_description)],
            ['label' => __('H1'), 'value' => $page->h1],
            ['label' => __('H1 count'), 'value' => $page->h1_count],
            ['label' => __('H2-1'), 'value' => $facts['h2'][0] ?? null],
            ['label' => __('H2-2'), 'value' => $facts['h2'][1] ?? null],
            ['label' => __('Canonical'), 'value' => $page->canonical],
            ['label' => __('Meta robots'), 'value' => $page->robots_meta],
            ['label' => __('X-Robots-Tag'), 'value' => $facts['headers']['x-robots-tag'] ?? null],
            ['label' => __('Word count'), 'value' => $page->word_count],
            ['label' => __('Crawl depth'), 'value' => $page->link_depth],
            ['label' => __('Inlinks'), 'value' => $page->inlinks],
            ['label' => __('Internal outlinks'), 'value' => $facts['internal_outlinks'] ?? 0],
            ['label' => __('Response time (ms)'), 'value' => $page->response_ms],
            ['label' => __('Size (bytes)'), 'value' => $page->bytes],
            ['label' => __('In sitemap'), 'value' => $page->is_in_sitemap ? __('Yes') : __('No')],
            ['label' => __('Structured data'), 'value' => isset($facts['structured_data']) ? (implode(', ', $facts['structured_data']['types'] ?? []) ?: __('None')) : null],
            ['label' => __('Visitors LCP / INP / CLS'), 'value' => isset($facts['vitals']) ? __(':lcp ms / :inp ms / :cls (:samples visits, :days days)', ['lcp' => $facts['vitals']['lcp'], 'inp' => $facts['vitals']['inp'], 'cls' => $facts['vitals']['cls'], 'samples' => number_format($facts['vitals']['samples']), 'days' => EnrichCrawlPages::DAYS]) : null],
            ['label' => __('Page views / visitors'), 'value' => isset($facts['visits']) ? __(':views / :visitors (:days days)', ['views' => number_format($facts['visits']['views']), 'visitors' => number_format($facts['visits']['visitors']), 'days' => EnrichCrawlPages::DAYS]) : null],
            ['label' => __('Google clicks / impressions'), 'value' => isset($facts['search']) ? __(':clicks / :impressions, position :position (:days days)', ['clicks' => number_format($facts['search']['clicks']), 'impressions' => number_format($facts['search']['impressions']), 'position' => $facts['search']['position'] ?? '-', 'days' => EnrichCrawlPages::DAYS]) : null],
            ['label' => __('Error'), 'value' => $page->fetch_error],
            $webpage && $webpage->shop && $webpage->website ? [
                'label' => __('Webpage'),
                'value' => $webpage->code,
                'link'  => route('grp.org.shops.show.web.webpages.show', [$webpage->organisation->slug, $webpage->shop->slug, $webpage->website->slug, $webpage->slug]),
            ] : null,
            $webpage ? $this->seoSuggestionsLink($webpage) : null,
        ], fn (?array $detail) => $detail !== null && $detail['value'] !== null && $detail['value'] !== ''));
    }

    /**
     * Where the SEO team reviews this page and writes its suggestions: the SEO review tab of the
     * product, or the edit page tab of the family. Other pages have no such review.
     *
     * @return array{label: string, value: string, link: string}|null
     */
    private function seoSuggestionsLink(Webpage $webpage): ?array
    {
        $model = $webpage->model;

        if (!$webpage->organisation || !$webpage->shop) {
            return null;
        }

        $link = match (true) {
            $model instanceof Product => route('grp.org.shops.show.catalogue.products.all_products.setting', [
                'organisation' => $webpage->organisation->slug,
                'shop'         => $webpage->shop->slug,
                'product'      => $model->slug,
                'tab'          => 'seo_review',
            ]),
            $model instanceof ProductCategory && $model->type === ProductCategoryTypeEnum::FAMILY => route('grp.org.shops.show.catalogue.families.show', [
                'organisation' => $webpage->organisation->slug,
                'shop'         => $webpage->shop->slug,
                'family'       => $model->slug,
                'tab'          => FamilyTabsEnum::EDIT_PAGE->value,
            ]),
            default => null,
        };

        return $link ? ['label' => __('SEO suggestions'), 'value' => __('Open the SEO review'), 'link' => $link] : null;
    }

    /**
     * @return array<int, array{type: string, label: string, severity: string, description: string, how_to_fix: string, details: array|null}>
     */
    private function issues(CrawlPage $page): array
    {
        $labels       = CrawlIssueTypeEnum::labels();
        $descriptions = CrawlIssueTypeEnum::descriptions();
        $howToFix     = CrawlIssueTypeEnum::howToFix();

        return $page->issues()
            ->get()
            ->sortBy(fn ($issue) => $issue->severity->rank())
            ->map(fn ($issue) => [
                'type'        => $issue->type->value,
                'label'       => $labels[$issue->type->value],
                'severity'    => $issue->severity->value,
                'description' => $descriptions[$issue->type->value],
                'how_to_fix'  => $howToFix[$issue->type->value],
                'details'     => $issue->details,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    private function inlinks(Crawl $crawl, string $urlHash): array
    {
        $query = DB::table('crawl_links')
            ->join('crawl_pages', fn ($join) => $join->on('crawl_pages.crawl_id', '=', 'crawl_links.crawl_id')->on('crawl_pages.url_hash', '=', 'crawl_links.from_url_hash'))
            ->where('crawl_links.crawl_id', $crawl->id)
            ->where('crawl_links.to_url_hash', $urlHash);

        return [
            'rows'  => (clone $query)
                ->orderBy('crawl_pages.url')
                ->limit(self::LINKS_SHOWN)
                ->get(['crawl_pages.url as from_url', 'crawl_links.kind', 'crawl_links.anchor', 'crawl_links.position', 'crawl_links.is_nofollow'])
                ->map(fn (object $link) => $this->linkRow($link))
                ->all(),
            'total' => $query->count(),
        ];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    private function outlinks(Crawl $crawl, string $urlHash, bool $images): array
    {
        $query = DB::table('crawl_links')
            ->leftJoin('crawl_pages', fn ($join) => $join->on('crawl_pages.crawl_id', '=', 'crawl_links.crawl_id')->on('crawl_pages.url_hash', '=', 'crawl_links.to_url_hash'))
            ->leftJoin('crawl_resources', fn ($join) => $join->on('crawl_resources.crawl_id', '=', 'crawl_links.crawl_id')->on('crawl_resources.url_hash', '=', 'crawl_links.to_url_hash'))
            ->where('crawl_links.crawl_id', $crawl->id)
            ->where('crawl_links.from_url_hash', $urlHash)
            ->when(
                $images,
                fn ($query) => $query->where('crawl_links.kind', CrawlLinkKindEnum::IMAGE->value),
                fn ($query) => $query->where('crawl_links.kind', '!=', CrawlLinkKindEnum::IMAGE->value)
            );

        return [
            'rows'  => (clone $query)
                ->orderBy('crawl_links.id')
                ->limit(self::LINKS_SHOWN)
                ->get([
                    'crawl_links.to_url',
                    'crawl_links.to_url_hash',
                    'crawl_links.kind',
                    'crawl_links.anchor',
                    'crawl_links.position',
                    'crawl_links.is_nofollow',
                    'crawl_links.is_internal',
                    DB::raw('coalesce(crawl_pages.status_code, crawl_resources.status_code) as status_code'),
                    'crawl_resources.bytes',
                ])
                ->map(fn (object $link) => $this->linkRow($link))
                ->all(),
            'total' => $query->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function linkRow(object $link): array
    {
        $kindLabels     = CrawlLinkKindEnum::labels();
        $positionLabels = CrawlLinkPositionEnum::labels();

        return [
            ...(array) $link,
            'kind'        => $kindLabels[$link->kind] ?? $link->kind,
            'position'    => $link->position ? ($positionLabels[$link->position] ?? $link->position) : null,
            'is_nofollow' => (bool) $link->is_nofollow,
        ];
    }

    public function asController(Crawl $crawl, ActionRequest $request): array
    {
        abort_unless($crawl->type === CrawlTypeEnum::AUDIT, 404);

        $this->crawl = $crawl;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($crawl, (string) $request->query('url_hash'));
    }
}
