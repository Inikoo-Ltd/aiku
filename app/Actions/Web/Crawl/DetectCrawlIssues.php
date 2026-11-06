<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl;

use App\Enums\Web\Crawl\CrawlIssueSeverityEnum;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\Web\Crawl;
use App\Models\Web\CrawlIssue;
use App\Models\Web\Website;
use App\Actions\Web\WebVital\UI\IndexWebpagesPageSpeed;
use App\Services\SiteAudit\SerpPixelWidth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

class DetectCrawlIssues
{
    use AsObject;

    private const int DUPLICATES_SHOWN = 5;

    private const int SIMHASH_BANDS = 4;

    private const int NEAR_DUPLICATE_MAX_BITS = 3;

    private const array PAGE_COLUMNS = [
        'id', 'url', 'url_hash', 'depth', 'link_depth', 'status_code', 'redirect_to', 'response_ms', 'content_type', 'title', 'meta_description',
        'canonical', 'robots_meta', 'h1_count', 'h1', 'images_without_alt', 'inlinks', 'is_in_sitemap', 'sitemap_count', 'is_indexable',
        'fetch_error', 'hreflang', 'word_count', 'content_hash', 'content_simhash', 'bytes',
    ];

    private const string HREFLANG_CODE_PATTERN = '/^(x-default|[a-z]{2,3}(-[a-z]{4})?(-([a-z]{2}|\d{3}))?)$/i';

    private array $issues = [];

    /** @var array<int, true> the crawled pages that are product webpages */
    private array $productPageIds = [];

    /** @var array<string, Collection<int, object>> the indexable pages by the text compared across pages, read with the facts */
    private array $factDuplicates = [];

    private int $crawlId;

    /**
     * @param  array<string, array<int, string>>  $linkedFrom
     * @param  array<string, array{urls: int, bytes: int}>  $sitemapFiles
     */
    public function handle(Crawl $crawl, array $linkedFrom = [], array $sitemapFiles = []): Crawl
    {
        $this->issues         = [];
        $this->crawlId        = $crawl->id;
        $this->factDuplicates = [];
        $this->productPageIds = DB::table('crawl_pages')
            ->join('webpages', 'webpages.id', '=', 'crawl_pages.webpage_id')
            ->where('crawl_pages.crawl_id', $crawl->id)
            ->where('webpages.sub_type', 'product')
            ->pluck('crawl_pages.id')
            ->flip()
            ->map(fn () => true)
            ->all();

        $pages = DB::table('crawl_pages')
            ->where('crawl_id', $crawl->id)
            ->select(self::PAGE_COLUMNS)
            ->get()
            ->keyBy('url_hash');

        $homePage = $pages->first(fn ($page) => $page->link_depth === 0);

        $redirectHops = [];

        foreach ($pages as $page) {
            $this->detectStatusIssues($page, $linkedFrom);

            if ($page->status_code >= 300 && $page->status_code < 400) {
                $redirectHops[$page->id] = $this->detectRedirectIssues($page, $pages, $linkedFrom);
            }

            $this->detectSitemapIssues($page);

            if ($this->isHtmlPage($page)) {
                $this->detectPageIssues($page, $pages);
                $this->detectUrlIssues($page);
                $this->detectContentIssues($page, $homePage !== null);
            }
        }

        $indexablePages = $pages->filter(fn ($page) => $page->is_indexable && $this->isHtmlPage($page));

        $this->detectDuplicates($indexablePages, 'title', CrawlIssueTypeEnum::DUPLICATE_TITLE);
        $this->detectDuplicates($indexablePages, 'meta_description', CrawlIssueTypeEnum::DUPLICATE_META_DESCRIPTION);
        $this->detectDuplicates($indexablePages, 'h1', CrawlIssueTypeEnum::DUPLICATE_H1);
        $this->detectDuplicateContent($indexablePages);
        $this->detectHreflangIssues($crawl, $pages, $indexablePages);
        $this->detectFactIssues($crawl, $pages);
        $this->detectUnlinkedCanonicals($crawl, $pages);

        if ($homePage) {
            $this->detectOversizedSitemaps($homePage, $sitemapFiles);
        }

        DB::transaction(function () use ($crawl, $redirectHops) {
            CrawlIssue::where('crawl_id', $crawl->id)->delete();

            $now = now();

            foreach (array_chunk($this->issues, 1000) as $chunk) {
                CrawlIssue::insert(array_map(fn (array $issue) => [
                    'crawl_id'      => $crawl->id,
                    'crawl_page_id' => $issue['crawl_page_id'],
                    'type'          => $issue['type']->value,
                    'severity'      => $issue['type']->severity()->value,
                    'details'       => $issue['details'] ? json_encode($issue['details']) : null,
                    'created_at'    => $now,
                ], $chunk));
            }

            foreach ($redirectHops as $crawlPageId => $hops) {
                DB::table('crawl_pages')->where('id', $crawlPageId)->update(['redirect_hops' => $hops]);
            }
        });

        $issuesBySeverity = collect($this->issues)->countBy(fn (array $issue) => $issue['type']->severity()->value);
        $pagesWithErrors  = collect($this->issues)
            ->filter(fn (array $issue) => $issue['type']->severity() === CrawlIssueSeverityEnum::ERROR)
            ->pluck('crawl_page_id')
            ->unique()
            ->count();

        $crawl->update([
            'number_errors'     => $issuesBySeverity->get(CrawlIssueSeverityEnum::ERROR->value, 0),
            'number_warnings'   => $issuesBySeverity->get(CrawlIssueSeverityEnum::WARNING->value, 0),
            'number_notices'    => $issuesBySeverity->get(CrawlIssueSeverityEnum::NOTICE->value, 0),
            'pages_with_errors' => $pagesWithErrors,
            'health_score'      => $pages->count() > 0 ? round(($pages->count() - $pagesWithErrors) * 100 / $pages->count(), 2) : null,
        ]);

        return $crawl;
    }

    private function detectStatusIssues(object $page, array $linkedFrom): void
    {
        $linkDetails = [
            'inlinks'       => (int) $page->inlinks,
            'linked_from'   => $linkedFrom[$page->url_hash] ?? [],
            'is_in_sitemap' => (bool) $page->is_in_sitemap,
        ];

        if ($page->status_code === null) {
            $this->addIssue($page, CrawlIssueTypeEnum::FETCH_FAILED, [...$linkDetails, 'error' => $page->fetch_error]);
        } elseif ($page->status_code >= 500) {
            $this->addIssue($page, CrawlIssueTypeEnum::HTTP_5XX, [...$linkDetails, 'status_code' => $page->status_code, 'sentry_url' => $this->sentryUrl($page)]);
        } elseif ($page->status_code >= 400) {
            $this->addIssue($page, CrawlIssueTypeEnum::HTTP_4XX, [...$linkDetails, 'status_code' => $page->status_code]);
        }

        if ($page->response_ms !== null && $page->response_ms > CrawlIssueTypeEnum::SLOW_RESPONSE_MS) {
            $this->addIssue($page, CrawlIssueTypeEnum::SLOW_RESPONSE, ['response_ms' => (int) $page->response_ms]);
        }
    }

    /**
     * The Sentry errors the audit raised on this page: every storefront request of an audit is
     * tagged `site_audit` with its id (TagSentryWithSiteAudit).
     */
    private function sentryUrl(object $page): string
    {
        return config('services.sentry_issues_url').'?'.http_build_query([
            'query'       => 'site_audit:'.$this->crawlId.' url:"'.$page->url.'"',
            'statsPeriod' => '14d',
        ]);
    }

    private function detectRedirectIssues(object $page, Collection $pages, array $linkedFrom): int
    {
        $hops     = 0;
        $visited  = [];
        $current  = $page;
        $isLoop   = false;
        $finalUrl = null;

        while ($current && $current->status_code >= 300 && $current->status_code < 400) {
            if (isset($visited[$current->url_hash])) {
                $isLoop = true;

                break;
            }

            $visited[$current->url_hash] = true;
            $hops++;
            $finalUrl = $current->redirect_to;
            $current  = $current->redirect_to ? $pages->get(md5($current->redirect_to)) : null;
        }

        if ($isLoop) {
            $this->addIssue($page, CrawlIssueTypeEnum::REDIRECT_LOOP, ['chain' => array_values(array_map(fn ($hash) => $pages->get($hash)?->url, array_keys($visited)))]);

            return $hops;
        }

        if ($hops > 1) {
            $this->addIssue($page, CrawlIssueTypeEnum::REDIRECT_CHAIN, ['hops' => $hops, 'final_url' => $finalUrl]);
        }

        if ($page->inlinks > 0) {
            $this->addIssue($page, CrawlIssueTypeEnum::LINKED_REDIRECT, [
                'inlinks'     => (int) $page->inlinks,
                'linked_from' => $linkedFrom[$page->url_hash] ?? [],
                'redirect_to' => $page->redirect_to,
            ]);
        }

        return $hops;
    }

    private function detectPageIssues(object $page, Collection $pages): void
    {
        $isNoindex = $page->robots_meta !== null && str_contains(strtolower($page->robots_meta), 'noindex');

        if ($page->is_in_sitemap && $isNoindex) {
            $this->addIssue($page, CrawlIssueTypeEnum::NOINDEX_IN_SITEMAP, ['robots_meta' => $page->robots_meta]);
        }

        if (!$page->is_indexable && ($page->canonical === null || $page->canonical === $page->url)) {
            $this->addIssue($page, CrawlIssueTypeEnum::NOINDEX_PAGE, ['robots_meta' => $page->robots_meta]);
        }

        if ($page->is_in_sitemap && !$isNoindex && $page->canonical !== null && $page->canonical !== $page->url) {
            $this->addIssue($page, CrawlIssueTypeEnum::SITEMAP_CANONICALISED, ['canonical' => $page->canonical]);
        }

        if ($page->bytes > CrawlIssueTypeEnum::HTML_MAX_MB * 1048576) {
            $this->addIssue($page, CrawlIssueTypeEnum::HTML_TOO_LARGE, ['text' => round($page->bytes / 1048576, 1).' MB']);
        }

        if ($page->images_without_alt > 0) {
            $this->addIssue($page, CrawlIssueTypeEnum::IMAGES_WITHOUT_ALT, ['images' => (int) $page->images_without_alt]);
        }

        if (!$isNoindex) {
            $this->detectCanonicalIssues($page, $pages);
        }

        if (!$page->is_indexable) {
            return;
        }

        $titleLength = mb_strlen((string) $page->title);

        if ($page->title === null) {
            $this->addIssue($page, CrawlIssueTypeEnum::MISSING_TITLE);
        } elseif ($titleLength > CrawlIssueTypeEnum::TITLE_MAX_LENGTH) {
            $this->addIssue($page, CrawlIssueTypeEnum::TITLE_TOO_LONG, ['title' => $page->title, 'length' => $titleLength]);
        } elseif ($titleLength < CrawlIssueTypeEnum::TITLE_MIN_LENGTH) {
            $this->addIssue($page, CrawlIssueTypeEnum::TITLE_TOO_SHORT, ['title' => $page->title, 'length' => $titleLength]);
        }

        $metaDescriptionLength = mb_strlen((string) $page->meta_description);

        if ($page->meta_description === null) {
            $this->addIssue($page, CrawlIssueTypeEnum::MISSING_META_DESCRIPTION);
        } elseif ($metaDescriptionLength > CrawlIssueTypeEnum::META_DESCRIPTION_MAX_LENGTH) {
            $this->addIssue($page, CrawlIssueTypeEnum::META_DESCRIPTION_TOO_LONG, ['length' => $metaDescriptionLength]);
        } elseif ($metaDescriptionLength < CrawlIssueTypeEnum::META_DESCRIPTION_MIN_LENGTH) {
            $this->addIssue($page, CrawlIssueTypeEnum::META_DESCRIPTION_TOO_SHORT, ['length' => $metaDescriptionLength]);
        }

        if ($page->h1 !== null && mb_strlen($page->h1) > CrawlIssueTypeEnum::HEADING_MAX_LENGTH) {
            $this->addIssue($page, CrawlIssueTypeEnum::H1_TOO_LONG, ['text' => $page->h1, 'length' => mb_strlen($page->h1)]);
        }

        if ($page->h1_count === 0) {
            $this->addIssue($page, CrawlIssueTypeEnum::MISSING_H1);
        } elseif ($page->h1_count > 1) {
            $this->addIssue($page, CrawlIssueTypeEnum::MULTIPLE_H1, ['h1_count' => (int) $page->h1_count]);
        }

        if (!$page->is_in_sitemap) {
            $this->addIssue($page, CrawlIssueTypeEnum::NOT_IN_SITEMAP);
        }
    }

    private function detectSitemapIssues(object $page): void
    {
        if (!$page->is_in_sitemap) {
            return;
        }

        if ($page->status_code !== 200) {
            $this->addIssue($page, CrawlIssueTypeEnum::SITEMAP_NON_200, ['status_code' => $page->status_code, 'redirect_to' => $page->redirect_to]);
        }

        if ($page->sitemap_count > 1) {
            $this->addIssue($page, CrawlIssueTypeEnum::IN_MULTIPLE_SITEMAPS, ['sitemaps' => (int) $page->sitemap_count]);
        }
    }

    private function detectUrlIssues(object $page): void
    {
        $path = (string) parse_url($page->url, PHP_URL_PATH);

        if ($path !== mb_strtolower($path)) {
            $this->addIssue($page, CrawlIssueTypeEnum::URL_UPPERCASE);
        }

        if (str_contains($path, '_')) {
            $this->addIssue($page, CrawlIssueTypeEnum::URL_UNDERSCORES);
        }

        if (str_contains($path, '//')) {
            $this->addIssue($page, CrawlIssueTypeEnum::URL_MULTIPLE_SLASHES);
        }

        if (preg_match('/[^\x00-\x7F]/', rawurldecode($path))) {
            $this->addIssue($page, CrawlIssueTypeEnum::URL_NON_ASCII);
        }

        if (str_contains($page->url, ' ') || str_contains(strtolower($page->url), '%20')) {
            $this->addIssue($page, CrawlIssueTypeEnum::URL_CONTAINS_SPACE);
        }

        $segments = array_values(array_filter(explode('/', mb_strtolower($path)), fn (string $segment) => $segment !== ''));

        if (count($segments) !== count(array_unique($segments))) {
            $this->addIssue($page, CrawlIssueTypeEnum::URL_REPETITIVE_PATH, ['text' => $path]);
        }

        if (mb_strlen($page->url) > CrawlIssueTypeEnum::URL_MAX_LENGTH) {
            $this->addIssue($page, CrawlIssueTypeEnum::URL_TOO_LONG, ['length' => mb_strlen($page->url)]);
        }
    }

    /**
     * Click depth and orphans need the home page's link depth, which audits that started before it
     * was recorded do not have, so those audits skip both checks.
     */
    private function detectContentIssues(object $page, bool $hasLinkDepths): void
    {
        if ($hasLinkDepths && $page->is_in_sitemap && $page->link_depth === null) {
            $this->addIssue($page, CrawlIssueTypeEnum::ORPHAN_PAGE);
        }

        if (!$page->is_indexable) {
            return;
        }

        if ($hasLinkDepths && $page->link_depth !== null && $page->link_depth >= CrawlIssueTypeEnum::HIGH_CRAWL_DEPTH_CLICKS) {
            $this->addIssue($page, CrawlIssueTypeEnum::HIGH_CRAWL_DEPTH, ['clicks' => (int) $page->link_depth]);
        }

        if ($page->word_count !== null && $page->word_count < CrawlIssueTypeEnum::LOW_CONTENT_WORDS) {
            $this->addIssue($page, CrawlIssueTypeEnum::LOW_CONTENT, ['words' => (int) $page->word_count]);
        }

        if ($page->title !== null && $page->h1 !== null && mb_strtolower($page->title) === mb_strtolower($page->h1)) {
            $this->addIssue($page, CrawlIssueTypeEnum::TITLE_SAME_AS_H1, ['title' => $page->title]);
        }
    }

    /**
     * Identical text by hash, and nearly identical text by simhash: two hashes within
     * `NEAR_DUPLICATE_MAX_BITS` bits share at least one of `SIMHASH_BANDS` 16-bit bands exactly, so
     * only pages sharing a band are compared.
     */
    private function detectDuplicateContent(Collection $indexablePages): void
    {
        $pagesWithText = $indexablePages->filter(fn ($page) => $page->content_hash !== null && $page->word_count >= CrawlIssueTypeEnum::LOW_CONTENT_WORDS);

        $this->detectDuplicates($pagesWithText, 'content_hash', CrawlIssueTypeEnum::EXACT_DUPLICATE);

        $bandBits = intdiv(64, self::SIMHASH_BANDS);
        $buckets  = [];

        foreach ($pagesWithText as $page) {
            $simhash = (int) $page->content_simhash;

            for ($band = 0; $band < self::SIMHASH_BANDS; $band++) {
                $buckets[$band.':'.(($simhash >> ($band * $bandBits)) & 0xFFFF)][] = $page;
            }
        }

        $nearDuplicates = [];

        foreach ($buckets as $bucket) {
            $count = count($bucket);

            for ($first = 0; $first < $count; $first++) {
                for ($second = $first + 1; $second < $count; $second++) {
                    [$a, $b] = [$bucket[$first], $bucket[$second]];

                    if ($a->content_hash === $b->content_hash || isset($nearDuplicates[$a->id][$b->id])) {
                        continue;
                    }

                    if ($this->differingBits((int) $a->content_simhash, (int) $b->content_simhash) <= self::NEAR_DUPLICATE_MAX_BITS) {
                        $nearDuplicates[$a->id][$b->id] = $b->url;
                        $nearDuplicates[$b->id][$a->id] = $a->url;
                    }
                }
            }
        }

        foreach ($nearDuplicates as $pageId => $urls) {
            $this->addIssue((object) ['id' => $pageId], CrawlIssueTypeEnum::NEAR_DUPLICATE, [
                'duplicates'     => count($urls),
                'duplicate_urls' => array_slice(array_values($urls), 0, self::DUPLICATES_SHOWN),
            ]);
        }
    }

    private function differingBits(int $a, int $b): int
    {
        $difference = $a ^ $b;
        $bits       = 0;

        for ($bit = 0; $bit < 64; $bit++) {
            $bits += ($difference >> $bit) & 1;
        }

        return $bits;
    }

    /**
     * The checks that read the page's facts (links, images, head tags, headers, social tags,
     * pagination), a few hundred pages at a time, since facts can be large.
     */
    private function detectFactIssues(Crawl $crawl, Collection $pages): void
    {
        $failedResources = DB::table('crawl_resources')
            ->where('crawl_id', $crawl->id)
            ->where(fn ($query) => $query->whereNull('status_code')
                ->orWhere('status_code', '>=', 400)
                ->orWhere(fn ($query) => $query->where('kind', 'image')->where('bytes', '>', CrawlIssueTypeEnum::LARGE_IMAGE_KB * 1024)))
            ->get(['url_hash', 'kind', 'status_code', 'bytes', 'fetch_error'])
            ->keyBy('url_hash');

        $pagesById = $pages->keyBy('id');

        DB::table('crawl_pages')
            ->where('crawl_id', $crawl->id)
            ->whereNotNull('facts')
            ->select(['id', 'facts'])
            ->chunkById(500, function (Collection $chunk) use ($pagesById, $pages, $failedResources) {
                foreach ($chunk as $row) {
                    $page = $pagesById->get($row->id);

                    if ($page) {
                        $this->detectPageFactIssues($page, json_decode($row->facts, true) ?: [], $pages, $failedResources);
                    }
                }
            });

        foreach ([
            'h2'             => CrawlIssueTypeEnum::DUPLICATE_H2,
            'og_title'       => CrawlIssueTypeEnum::DUPLICATE_OG_TITLE,
            'og_description' => CrawlIssueTypeEnum::DUPLICATE_OG_DESCRIPTION,
        ] as $field => $type) {
            $this->detectDuplicates(collect($this->factDuplicates[$field] ?? []), $field, $type);
        }
    }

    /**
     * Canonicals that name another URL of the website which no crawled page links to, so search
     * engines reach it only through the canonical. Audits that ran before links were recorded are
     * skipped.
     */
    private function detectUnlinkedCanonicals(Crawl $crawl, Collection $pages): void
    {
        $canonicalised = $pages->filter(fn ($page) => $this->isHtmlPage($page) && $page->canonical !== null && $page->canonical !== $page->url && $pages->has(md5($page->canonical)));

        if ($canonicalised->isEmpty() || !DB::table('crawl_links')->where('crawl_id', $crawl->id)->exists()) {
            return;
        }

        $linked = DB::table('crawl_links')
            ->where('crawl_id', $crawl->id)
            ->where('kind', 'hyperlink')
            ->whereIn('to_url_hash', $canonicalised->map(fn ($page) => md5($page->canonical))->unique()->values())
            ->distinct()
            ->pluck('to_url_hash')
            ->flip();

        foreach ($canonicalised as $page) {
            if (!$linked->has(md5($page->canonical))) {
                $this->addIssue($page, CrawlIssueTypeEnum::CANONICAL_UNLINKED, ['canonical' => $page->canonical]);
            }
        }
    }

    private function detectPageFactIssues(object $page, array $facts, Collection $pages, Collection $failedResources): void
    {
        foreach ([
            'nofollow_internal'  => CrawlIssueTypeEnum::INTERNAL_NOFOLLOW_LINKS,
            'non_descriptive'    => CrawlIssueTypeEnum::NON_DESCRIPTIVE_ANCHOR_TEXT,
            'localhost_links'    => CrawlIssueTypeEnum::LOCALHOST_LINKS,
            'unsafe_blank_links' => CrawlIssueTypeEnum::UNSAFE_BLANK_LINKS,
            'http_links'         => CrawlIssueTypeEnum::INTERNAL_HTTP_LINKS,
            'parameter_links'    => CrawlIssueTypeEnum::LINKS_WITH_PARAMETERS,
            'insecure_resources' => CrawlIssueTypeEnum::MIXED_CONTENT,
            'insecure_forms'     => CrawlIssueTypeEnum::INSECURE_FORMS,
        ] as $fact => $type) {
            if (!empty($facts[$fact])) {
                $this->addIssue($page, $type, $facts[$fact]);
            }
        }

        if (!empty($facts['is_nofollow'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::NOFOLLOW_PAGE, ['robots_meta' => $page->robots_meta]);
        }

        if (!empty($facts['soft_404'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::SOFT_404, ['text' => $facts['soft_404']]);
        }

        if (!empty($facts['ssr_fallback'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::SSR_FALLBACK, ['sentry_url' => $this->sentryUrl($page)]);
        }

        if (!empty($facts['lorem_ipsum'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::LOREM_IPSUM);
        }

        if (($facts['title_count'] ?? 0) > 1) {
            $this->addIssue($page, CrawlIssueTypeEnum::MULTIPLE_TITLES, ['count' => $facts['title_count']]);
        }

        if (($facts['meta_description_count'] ?? 0) > 1) {
            $this->addIssue($page, CrawlIssueTypeEnum::MULTIPLE_META_DESCRIPTIONS, ['count' => $facts['meta_description_count']]);
        }

        if (!empty($facts['canonicals'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::CONFLICTING_CANONICALS, ['items' => $facts['canonicals']]);
        }

        if (!empty($facts['head_tags_in_body'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::HEAD_TAGS_IN_BODY, ['items' => $facts['head_tags_in_body']]);
        }

        if (!empty($facts['missing_headers'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::MISSING_SECURITY_HEADERS, ['items' => $facts['missing_headers']]);
        }

        if (($facts['internal_outlinks'] ?? 0) === 0) {
            $this->addIssue($page, CrawlIssueTypeEnum::NO_INTERNAL_OUTLINKS);
        }

        if (($facts['empty_anchors'] ?? 0) > 0) {
            $this->addIssue($page, CrawlIssueTypeEnum::EMPTY_ANCHOR_TEXT, ['count' => $facts['empty_anchors']]);
        }

        if (($facts['images_without_size'] ?? 0) > 0) {
            $this->addIssue($page, CrawlIssueTypeEnum::IMAGES_WITHOUT_SIZE, ['count' => $facts['images_without_size']]);
        }

        $this->detectResourceIssues($page, $facts, $failedResources);
        $this->detectPaginationIssues($page, $facts, $pages);
        $this->detectValidationIssues($page, $facts);
        $this->detectFieldDataIssues($page, $facts);

        if (!$page->is_indexable) {
            return;
        }

        $this->detectIndexablePageFactIssues($page, $facts);

        if (($facts['h2_count'] ?? 0) === 0) {
            $this->addIssue($page, CrawlIssueTypeEnum::MISSING_H2);
        }

        if (empty($facts['has_open_graph'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::OPEN_GRAPH_MISSING);
        } elseif (!empty($facts['og_missing'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::OPEN_GRAPH_INCOMPLETE, ['items' => $facts['og_missing']]);
        }

        if (empty($facts['has_twitter_card'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::TWITTER_CARD_MISSING);
        }
    }

    /**
     * The checks on how the page is written that apply to every page: links, directives, refreshes,
     * document structure and social tags.
     */
    private function detectValidationIssues(object $page, array $facts): void
    {
        foreach ([
            'tracking_links'    => CrawlIssueTypeEnum::LINKS_WITH_TRACKING_PARAMETERS,
            'broken_bookmarks'  => CrawlIssueTypeEnum::BROKEN_BOOKMARK,
            'protocol_relative' => CrawlIssueTypeEnum::PROTOCOL_RELATIVE_LINKS,
            'long_alt_texts'    => CrawlIssueTypeEnum::ALT_TEXT_TOO_LONG,
        ] as $fact => $type) {
            if (!empty($facts[$fact])) {
                $this->addIssue($page, $type, $facts[$fact]);
            }
        }

        foreach ([
            'missing_doctype'  => CrawlIssueTypeEnum::MISSING_DOCTYPE,
            'missing_head'     => CrawlIssueTypeEnum::MISSING_HEAD,
            'missing_body'     => CrawlIssueTypeEnum::MISSING_BODY,
            'missing_viewport' => CrawlIssueTypeEnum::MISSING_VIEWPORT,
            'missing_charset'  => CrawlIssueTypeEnum::MISSING_CHARSET,
            'og_image_relative' => CrawlIssueTypeEnum::OG_IMAGE_RELATIVE,
            'alt_in_h1'        => CrawlIssueTypeEnum::ALT_TEXT_IN_H1,
            'h1_not_first'     => CrawlIssueTypeEnum::H1_NOT_FIRST_HEADING,
        ] as $fact => $type) {
            if (!empty($facts[$fact])) {
                $this->addIssue($page, $type);
            }
        }

        if (!empty($facts['multiple_heads'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::MULTIPLE_HEADS, ['count' => $facts['multiple_heads']]);
        }

        if (!empty($facts['multiple_bodies'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::MULTIPLE_BODIES, ['count' => $facts['multiple_bodies']]);
        }

        if (!empty($facts['invalid_head_elements'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::INVALID_HEAD_ELEMENTS, ['items' => $facts['invalid_head_elements']]);
        }

        if (!empty($facts['heading_skips'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::HEADING_LEVEL_SKIPPED, ['items' => $facts['heading_skips']]);
        }

        if (!empty($facts['social_multiple'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::OPEN_GRAPH_MULTIPLE, ['items' => $facts['social_multiple']]);
        }

        if (!empty($facts['meta_refresh']) || !empty($facts['http_refresh'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::META_REFRESH, ['text' => $facts['meta_refresh'] ?? $facts['http_refresh']]);
        }

        if (($facts['javascript_links'] ?? 0) > 0) {
            $this->addIssue($page, CrawlIssueTypeEnum::JAVASCRIPT_LINKS, ['count' => $facts['javascript_links']]);
        }

        if (($facts['internal_outlinks'] ?? 0) > CrawlIssueTypeEnum::MAX_INTERNAL_OUTLINKS) {
            $this->addIssue($page, CrawlIssueTypeEnum::HIGH_INTERNAL_OUTLINKS, ['count' => $facts['internal_outlinks']]);
        }

        if (($facts['external_outlinks'] ?? 0) > CrawlIssueTypeEnum::MAX_EXTERNAL_OUTLINKS) {
            $this->addIssue($page, CrawlIssueTypeEnum::HIGH_EXTERNAL_OUTLINKS, ['count' => $facts['external_outlinks']]);
        }

        if (!empty($facts['canonical_relative'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::CANONICAL_RELATIVE, ['canonical' => $page->canonical]);
        }

        if (!empty($facts['canonical_fragment'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::CANONICAL_WITH_FRAGMENT, ['canonical' => $page->canonical]);
        }

        foreach ([
            'nosnippet'         => CrawlIssueTypeEnum::NOSNIPPET_PAGE,
            'noimageindex'      => CrawlIssueTypeEnum::NOIMAGEINDEX_PAGE,
            'unavailable_after' => CrawlIssueTypeEnum::UNAVAILABLE_AFTER_PAGE,
        ] as $fact => $type) {
            if (!empty($facts[$fact])) {
                $this->addIssue($page, $type, ['robots_meta' => $page->robots_meta]);
            }
        }
    }

    /**
     * The checks on what the page tells Google in its JSON-LD, and on what real visitors and Google
     * search recorded for it in the last weeks.
     */
    private function detectFieldDataIssues(object $page, array $facts): void
    {
        $structuredData = $facts['structured_data'] ?? null;

        if ($structuredData !== null) {
            if (!empty($structuredData['errors'])) {
                $this->addIssue($page, CrawlIssueTypeEnum::INVALID_STRUCTURED_DATA, ['errors' => $structuredData['errors']]);
            } elseif (!empty($structuredData['warnings'])) {
                $this->addIssue($page, CrawlIssueTypeEnum::STRUCTURED_DATA_WARNINGS, ['items' => $structuredData['warnings']]);
            }

            if (isset($this->productPageIds[$page->id]) && array_intersect($structuredData['types'] ?? [], ['Product', 'ProductGroup']) === []) {
                $this->addIssue($page, CrawlIssueTypeEnum::MISSING_PRODUCT_STRUCTURED_DATA, ['items' => $structuredData['types'] ?? []]);
            }
        }

        $vitals = $facts['vitals'] ?? null;

        if ($vitals !== null) {
            if (($vitals['lcp'] ?? 0) > IndexWebpagesPageSpeed::LCP_LIMITS[1]) {
                $this->addIssue($page, CrawlIssueTypeEnum::POOR_LCP, ['text' => round($vitals['lcp'] / 1000, 1).' s', 'count' => $vitals['samples']]);
            }

            if (($vitals['inp'] ?? 0) > IndexWebpagesPageSpeed::INP_LIMITS[1]) {
                $this->addIssue($page, CrawlIssueTypeEnum::POOR_INP, ['text' => $vitals['inp'].' ms', 'count' => $vitals['samples']]);
            }

            if (($vitals['cls'] ?? 0) > IndexWebpagesPageSpeed::CLS_LIMITS[1]) {
                $this->addIssue($page, CrawlIssueTypeEnum::POOR_CLS, ['text' => (string) $vitals['cls'], 'count' => $vitals['samples']]);
            }
        }

        $search = $facts['search'] ?? null;

        if ($search !== null && $this->isHtmlPage($page)) {
            if ($page->is_indexable && (int) $search['impressions'] === 0) {
                $this->addIssue($page, CrawlIssueTypeEnum::INDEXABLE_WITHOUT_IMPRESSIONS);
            } elseif (!$page->is_indexable && (int) $search['clicks'] > 0) {
                $this->addIssue($page, CrawlIssueTypeEnum::NON_INDEXABLE_WITH_CLICKS, ['count' => (int) $search['clicks']]);
            }
        }

        $visits = $facts['visits'] ?? null;

        if ($visits !== null && $this->isHtmlPage($page)) {
            if ($page->is_indexable && (int) $visits['views'] === 0) {
                $this->addIssue($page, CrawlIssueTypeEnum::INDEXABLE_WITHOUT_VISITS);
            } elseif (!$page->is_indexable && (int) $visits['views'] > 0) {
                $this->addIssue($page, CrawlIssueTypeEnum::NON_INDEXABLE_WITH_VISITS, ['count' => (int) $visits['views']]);
            }
        }
    }

    /**
     * The checks that only matter for pages meant to be found in search: widths in results, headings,
     * readability, and the texts compared across pages.
     */
    private function detectIndexablePageFactIssues(object $page, array $facts): void
    {
        if (($facts['title_px'] ?? 0) > SerpPixelWidth::TITLE_MAX_PIXELS) {
            $this->addIssue($page, CrawlIssueTypeEnum::TITLE_OVER_PIXELS, ['text' => $page->title, 'pixels' => $facts['title_px']]);
        }

        if (($facts['meta_description_px'] ?? 0) > SerpPixelWidth::DESCRIPTION_MAX_PIXELS) {
            $this->addIssue($page, CrawlIssueTypeEnum::META_DESCRIPTION_OVER_PIXELS, ['pixels' => $facts['meta_description_px']]);
        }

        $longH2 = array_values(array_filter($facts['h2'] ?? [], fn (string $h2) => mb_strlen($h2) > CrawlIssueTypeEnum::HEADING_MAX_LENGTH));

        if ($longH2) {
            $this->addIssue($page, CrawlIssueTypeEnum::H2_TOO_LONG, ['items' => $longH2]);
        }

        $readingEase = $facts['reading_ease'] ?? null;

        if ($readingEase !== null && $readingEase < CrawlIssueTypeEnum::READING_EASE_VERY_DIFFICULT) {
            $this->addIssue($page, CrawlIssueTypeEnum::READABILITY_VERY_DIFFICULT, ['score' => $readingEase]);
        } elseif ($readingEase !== null && $readingEase < CrawlIssueTypeEnum::READING_EASE_DIFFICULT) {
            $this->addIssue($page, CrawlIssueTypeEnum::READABILITY_DIFFICULT, ['score' => $readingEase]);
        }

        foreach (['h2' => $facts['h2'][0] ?? null, 'og_title' => $facts['og_title'] ?? null, 'og_description' => $facts['og_description'] ?? null] as $field => $value) {
            if ($value !== null && $value !== '') {
                $this->factDuplicates[$field][] = (object) ['id' => $page->id, 'url' => $page->url, $field => $value];
            }
        }
    }

    private function detectResourceIssues(object $page, array $facts, Collection $failedResources): void
    {
        $broken = fn (?object $resource) => $this->isBrokenResource($resource);
        $found  = fn (array $urls) => collect($urls)->map(fn (string $url) => ['url' => $url, 'resource' => $failedResources->get(md5($url))]);
        $listed = fn (Collection $items) => [
            'count' => $items->count(),
            'items' => $items->take(self::DUPLICATES_SHOWN)->map(fn (array $item) => [
                'url'         => $item['url'],
                'status_code' => $item['resource']->status_code,
                'kb'          => $item['resource']->bytes ? (int) round($item['resource']->bytes / 1024) : null,
                'error'       => $item['resource']->fetch_error ? Str::limit($item['resource']->fetch_error, 120) : null,
            ])->values()->all(),
        ];

        $brokenLinks = $found($facts['external_links'] ?? [])->filter(fn (array $item) => $broken($item['resource']));

        if ($brokenLinks->isNotEmpty()) {
            $this->addIssue($page, CrawlIssueTypeEnum::BROKEN_EXTERNAL_LINKS, $listed($brokenLinks));
        }

        $images       = $found($facts['images'] ?? []);
        $brokenImages = $images->filter(fn (array $item) => $broken($item['resource']));
        $largeImages  = $images->filter(fn (array $item) => $item['resource'] && $item['resource']->status_code < 400 && $item['resource']->bytes > CrawlIssueTypeEnum::LARGE_IMAGE_KB * 1024);

        if ($brokenImages->isNotEmpty()) {
            $this->addIssue($page, CrawlIssueTypeEnum::BROKEN_IMAGES, $listed($brokenImages));
        }

        if ($largeImages->isNotEmpty()) {
            $this->addIssue($page, CrawlIssueTypeEnum::LARGE_IMAGES, $listed($largeImages));
        }

        $ogImage = $found(array_filter([$facts['og_image'] ?? null]));

        if ($ogImage->filter(fn (array $item) => $broken($item['resource']))->isNotEmpty()) {
            $this->addIssue($page, CrawlIssueTypeEnum::OG_IMAGE_BROKEN, $listed($ogImage));
        } elseif ($ogImage->filter(fn (array $item) => $item['resource'] && $item['resource']->bytes > CrawlIssueTypeEnum::OG_IMAGE_MAX_MB * 1048576)->isNotEmpty()) {
            $this->addIssue($page, CrawlIssueTypeEnum::OG_IMAGE_TOO_LARGE, $listed($ogImage));
        }
    }

    /**
     * A resource is broken when its server answers with an error, or for a link when its domain no
     * longer exists. Timeouts and connection errors are not counted: they come and go, and a busy
     * audit worker causes them as often as the other server does.
     */
    private function isBrokenResource(?object $resource): bool
    {
        if (!$resource) {
            return false;
        }

        if ($resource->status_code === null) {
            return $resource->kind === 'link' && str_contains((string) $resource->fetch_error, 'Could not resolve host');
        }

        return $resource->status_code >= 400 && !in_array($resource->status_code, AuditWebsite::UNCHECKABLE_STATUSES, true);
    }

    private function detectPaginationIssues(object $page, array $facts, Collection $pages): void
    {
        $pagination = $facts['pagination'] ?? [];

        if ($pagination === []) {
            return;
        }

        if (in_array($page->url, $pagination, true)) {
            $this->addIssue($page, CrawlIssueTypeEnum::PAGINATION_LOOP, ['items' => array_values($pagination)]);
        }

        if (!empty($facts['pagination_unlinked'])) {
            $this->addIssue($page, CrawlIssueTypeEnum::PAGINATION_NOT_LINKED, ['items' => $facts['pagination_unlinked']]);
        }

        $broken = collect($pagination)
            ->map(fn (string $url) => $pages->get(md5($url)))
            ->filter(fn (?object $target) => $target && $target->status_code !== 200)
            ->map(fn (object $target) => ['url' => $target->url, 'status_code' => $target->status_code])
            ->values();

        if ($broken->isNotEmpty()) {
            $this->addIssue($page, CrawlIssueTypeEnum::PAGINATION_TO_BROKEN, ['count' => $broken->count(), 'items' => $broken->all()]);
        }
    }

    /**
     * @param  array<string, array{urls: int, bytes: int}>  $sitemapFiles
     */
    private function detectOversizedSitemaps(object $homePage, array $sitemapFiles): void
    {
        $oversized = collect($sitemapFiles)
            ->filter(fn (array $file) => $file['urls'] > AuditWebsite::SITEMAP_MAX_URLS || $file['bytes'] > AuditWebsite::SITEMAP_MAX_BYTES)
            ->map(fn (array $file, string $url) => ['url' => $url, 'urls' => $file['urls'], 'mb' => round($file['bytes'] / 1048576, 1)])
            ->values();

        if ($oversized->isNotEmpty()) {
            $this->addIssue($homePage, CrawlIssueTypeEnum::SITEMAP_TOO_LARGE, ['count' => $oversized->count(), 'items' => $oversized->all()]);
        }
    }

    private function detectCanonicalIssues(object $page, Collection $pages): void
    {
        if ($page->canonical === null) {
            $this->addIssue($page, CrawlIssueTypeEnum::MISSING_CANONICAL);

            return;
        }

        if ($page->canonical === $page->url) {
            return;
        }

        $canonicalPage = $pages->get(md5($page->canonical));

        if ($canonicalPage && $canonicalPage->status_code !== 200) {
            $this->addIssue($page, CrawlIssueTypeEnum::CANONICAL_TO_BROKEN, ['canonical' => $page->canonical, 'status_code' => $canonicalPage->status_code]);

            return;
        }

        if ($canonicalPage && $this->isHtmlPage($canonicalPage) && !$canonicalPage->is_indexable) {
            $this->addIssue($page, CrawlIssueTypeEnum::CANONICAL_TO_NON_INDEXABLE, [
                'canonical'     => $page->canonical,
                'robots_meta'   => $canonicalPage->robots_meta,
                'its_canonical' => $canonicalPage->canonical !== $canonicalPage->url ? $canonicalPage->canonical : null,
            ]);
        }

        $this->addIssue($page, CrawlIssueTypeEnum::CANONICAL_TO_OTHER_PAGE, ['canonical' => $page->canonical]);
    }

    private function detectDuplicates(Collection $indexablePages, string $field, CrawlIssueTypeEnum $type): void
    {
        $indexablePages
            ->filter(fn ($page) => $page->{$field} !== null)
            ->groupBy(fn ($page) => mb_strtolower($page->{$field}))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->each(function (Collection $group) use ($type) {
                foreach ($group as $page) {
                    $this->addIssue($page, $type, [
                        'duplicates'      => $group->count() - 1,
                        'duplicate_urls'  => $group->where('id', '!=', $page->id)->take(self::DUPLICATES_SHOWN)->pluck('url')->values()->all(),
                    ]);
                }
            });
    }

    private function detectHreflangIssues(Crawl $crawl, Collection $pages, Collection $indexablePages): void
    {
        $alternatesByPage = $indexablePages
            ->filter(fn ($page) => $page->hreflang !== null)
            ->mapWithKeys(fn ($page) => [$page->id => $this->hreflangAlternates($page->hreflang)])
            ->filter();

        if ($alternatesByPage->isEmpty()) {
            return;
        }

        $targets = $this->hreflangTargets($crawl, $pages, $alternatesByPage);

        foreach ($indexablePages as $page) {
            $alternates = collect($alternatesByPage->get($page->id, []));

            if ($alternates->isEmpty()) {
                continue;
            }

            $pageUrl = $this->normaliseUrl($page->url);

            $invalidCodes = $alternates->pluck('hreflang')
                ->reject(fn (string $code) => preg_match(self::HREFLANG_CODE_PATTERN, $code))
                ->unique()
                ->values();

            if ($invalidCodes->isNotEmpty()) {
                $this->addIssue($page, CrawlIssueTypeEnum::HREFLANG_INVALID_CODE, ['codes' => $invalidCodes->all()]);
            }

            $conflictingCodes = $alternates
                ->groupBy(fn (array $alternate) => strtolower($alternate['hreflang']))
                ->filter(fn (Collection $group) => $group->pluck('url')->unique()->count() > 1)
                ->keys();

            if ($conflictingCodes->isNotEmpty()) {
                $this->addIssue($page, CrawlIssueTypeEnum::HREFLANG_CONFLICTING_CODE, ['codes' => $conflictingCodes->all()]);
            }

            if (!$alternates->contains('url', $pageUrl)) {
                $this->addIssue($page, CrawlIssueTypeEnum::HREFLANG_MISSING_SELF, ['alternates' => $alternates->count()]);
            }

            if (!$alternates->contains(fn (array $alternate) => strtolower($alternate['hreflang']) === 'x-default')) {
                $this->addIssue($page, CrawlIssueTypeEnum::HREFLANG_MISSING_X_DEFAULT, ['alternates' => $alternates->count()]);
            }

            $nonCanonical = [];
            $noindex      = [];

            $broken        = [];
            $missingReturn = [];

            foreach ($alternates->pluck('url')->unique() as $url) {
                $target = $targets->get(md5($url));

                if ($url === $pageUrl || !$target) {
                    continue;
                }

                if ($target->status_code === 200 && isset($target->canonical) && $target->canonical !== null && $target->canonical !== $target->url) {
                    $nonCanonical[] = $url;
                }

                if ($target->status_code === 200 && isset($target->robots_meta) && str_contains(strtolower((string) $target->robots_meta), 'noindex')) {
                    $noindex[] = $url;
                }

                if ($target->status_code !== 200) {
                    $broken[] = ['url' => $url, 'status_code' => $target->status_code];
                } elseif (($target->hreflang_audited ?? true) && !collect($this->hreflangAlternates($target->hreflang))->contains('url', $pageUrl)) {
                    $missingReturn[] = $url;
                }
            }

            if ($broken) {
                $this->addIssue($page, CrawlIssueTypeEnum::HREFLANG_TO_BROKEN, ['count' => count($broken), 'alternates' => array_slice($broken, 0, self::DUPLICATES_SHOWN)]);
            }

            if ($missingReturn) {
                $this->addIssue($page, CrawlIssueTypeEnum::HREFLANG_MISSING_RETURN, ['count' => count($missingReturn), 'alternates' => array_slice($missingReturn, 0, self::DUPLICATES_SHOWN)]);
            }

            if ($nonCanonical) {
                $this->addIssue($page, CrawlIssueTypeEnum::HREFLANG_TO_NON_CANONICAL, ['count' => count($nonCanonical), 'alternates' => array_slice($nonCanonical, 0, self::DUPLICATES_SHOWN)]);
            }

            if ($noindex) {
                $this->addIssue($page, CrawlIssueTypeEnum::HREFLANG_TO_NOINDEX, ['count' => count($noindex), 'alternates' => array_slice($noindex, 0, self::DUPLICATES_SHOWN)]);
            }
        }
    }

    /**
     * The crawled page behind each alternate URL, from this audit for the website's own pages and
     * from the latest audit of the other website for the rest. URLs with no audited page are left
     * out, because nothing can be said about them, and the return link is not checked against an
     * audit that ran before hreflang was recorded.
     *
     * @return Collection<string, object>
     */
    private function hreflangTargets(Crawl $crawl, Collection $pages, Collection $alternatesByPage): Collection
    {
        $targets       = collect();
        $urlsByWebsite = [];

        foreach ($alternatesByPage->flatten(1)->pluck('url')->unique() as $url) {
            $hash = md5($url);

            if ($pages->has($hash)) {
                $targets->put($hash, $pages->get($hash));

                continue;
            }

            $host = preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST));

            $urlsByWebsite[$host][] = $hash;
        }

        $websiteIds = Website::whereIn('domain', array_keys($urlsByWebsite))->pluck('id', 'domain');

        foreach ($urlsByWebsite as $host => $hashes) {
            $websiteId = $websiteIds->get($host);

            if (!$websiteId || $websiteId === $crawl->website_id) {
                continue;
            }

            $latestAuditId = Crawl::where('website_id', $websiteId)
                ->where('type', CrawlTypeEnum::AUDIT)
                ->where('is_full_audit', true)
                ->where('state', CrawlStateEnum::FINISH)
                ->whereNotNull('health_score')
                ->latest('id')
                ->value('id');

            if (!$latestAuditId) {
                continue;
            }

            $hreflangAudited = DB::table('crawl_pages')
                ->where('crawl_id', $latestAuditId)
                ->whereNotNull('hreflang')
                ->exists();

            foreach (array_chunk($hashes, 1000) as $chunk) {
                DB::table('crawl_pages')
                    ->where('crawl_id', $latestAuditId)
                    ->whereIn('url_hash', $chunk)
                    ->get(['url_hash', 'status_code', 'hreflang'])
                    ->each(function ($target) use ($targets, $hreflangAudited) {
                        $target->hreflang_audited = $hreflangAudited;
                        $targets->put($target->url_hash, $target);
                    });
            }
        }

        return $targets;
    }

    /**
     * @return array<int, array{hreflang: string, url: string}>
     */
    private function hreflangAlternates(?string $hreflang): array
    {
        $alternates = [];

        foreach (json_decode((string) $hreflang, true) ?: [] as $alternate) {
            $url = $this->normaliseUrl($alternate['href'] ?? null);

            if ($url) {
                $alternates[] = ['hreflang' => (string) ($alternate['hreflang'] ?? ''), 'url' => $url];
            }
        }

        return $alternates;
    }

    private function normaliseUrl(?string $url): ?string
    {
        $parts = parse_url((string) $url);

        if (!$parts || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return strtolower($parts['scheme']).'://'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function isHtmlPage(object $page): bool
    {
        return $page->status_code === 200 && str_contains(strtolower((string) $page->content_type), 'html');
    }

    private function addIssue(object $page, CrawlIssueTypeEnum $type, ?array $details = null): void
    {
        $this->issues[] = [
            'crawl_page_id' => $page->id,
            'type'          => $type,
            'details'       => $details,
        ];
    }
}
