<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Created: Fri, 16 Oct 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Web\Crawl\AuditWebsite;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\Web\Crawl;
use App\Models\Web\CrawlIssue;
use App\Models\Web\CrawlPage;
use App\Models\Web\Webpage;
use App\Services\SiteAudit\AuditedHtml;
use App\Services\SiteAudit\RichResultsTest;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Client\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * "Crawl page" on a catalogue webpage: fetches only that webpage, at once, and runs on it the page
 * checks of DetectCrawlIssues. A site audit crawls the whole website, which takes minutes and waits
 * in the queue. The result is cached rather than stored as a crawl, so it never stands in for the
 * website's latest audit; sitemap and finding broken links still come from that audit.
 */
trait WithWebpageCheck
{
    public const array TITLE_ISSUES = [CrawlIssueTypeEnum::MISSING_TITLE, CrawlIssueTypeEnum::TITLE_TOO_LONG, CrawlIssueTypeEnum::TITLE_TOO_SHORT, CrawlIssueTypeEnum::DUPLICATE_TITLE];

    public const array META_DESCRIPTION_ISSUES = [CrawlIssueTypeEnum::MISSING_META_DESCRIPTION, CrawlIssueTypeEnum::META_DESCRIPTION_TOO_LONG, CrawlIssueTypeEnum::META_DESCRIPTION_TOO_SHORT, CrawlIssueTypeEnum::DUPLICATE_META_DESCRIPTION];

    /**
     * Why the crawl failed a filled field, so a title that is filled in but too long reads as such.
     * The page title is measured as rendered, with the website prefix and suffix around it.
     *
     * @param  array<int, CrawlIssueTypeEnum>  $types
     */
    public static function issueNote(?CrawlPage $crawlPage, array $types, Webpage $webpage): ?string
    {
        $issue = $crawlPage?->issues->first(fn (CrawlIssue $issue) => in_array($issue->type, $types, true));

        if (!$issue) {
            return null;
        }

        $length = Arr::get($issue->details ?? [], 'length');
        $note   = CrawlIssueTypeEnum::descriptions()[$issue->type->value].($length ? ' '.__('It is :length characters now.', ['length' => $length]) : '');

        if ($issue->type === CrawlIssueTypeEnum::TITLE_TOO_LONG && Arr::get($webpage->seo_data, 'use_title_prefix_suffix', true)) {
            $note .= ' '.__('That includes the title prefix and suffix, which Title extras can switch off.');
        }

        return $note;
    }

    public static function cacheKey(Webpage $webpage): string
    {
        return "webpage-check:$webpage->id";
    }

    /**
     * @return array{checked_at: string, url: string, status_code: ?int, response_ms: ?int, fetch_error: ?string, issues: array<int, array{type: string, details: ?array}>, is_allowed_by_robots_txt?: bool, internal_links?: ?int, is_content_in_html?: ?bool, structured_data?: ?array{is_valid: bool, unparsable: int, items: array}, broken_links?: ?array<int, array{url: string, status_code: ?int}>}|null
     */
    public static function cached(Webpage $webpage): ?array
    {
        return Cache::get(self::cacheKey($webpage));
    }

    /**
     * The broken links the page check found still broken, when it ran after the latest site audit;
     * null when the audit is newer or the check could not read them, so the audit answers.
     *
     * @return array<int, array{url: string, status_code: ?int}>|null
     */
    public static function checkedBrokenLinks(Webpage $webpage, ?CrawlPage $auditPage): ?array
    {
        $check = self::cached($webpage);

        if (!isset($check['broken_links']) || ($auditPage?->crawl->end_at && $auditPage->crawl->end_at->gte(Carbon::parse($check['checked_at'])))) {
            return null;
        }

        return $check['broken_links'];
    }

    /**
     * The webpage checked on its own after the latest site audit stands in for the audited page, as
     * an unsaved crawl page. Sitemap issues need the whole website, so they stay those of the audit.
     */
    public static function checkedPage(Webpage $webpage, ?CrawlPage $auditPage): ?CrawlPage
    {
        $check     = self::cached($webpage);
        $checkedAt = $check ? Carbon::parse($check['checked_at']) : null;

        if (!$checkedAt || ($auditPage?->crawl->end_at && $auditPage->crawl->end_at->gte($checkedAt))) {
            return $auditPage;
        }

        $sitemapTypes = [CrawlIssueTypeEnum::NOT_IN_SITEMAP, CrawlIssueTypeEnum::NOINDEX_IN_SITEMAP];
        $issues       = collect($check['issues'])
            ->map(function (array $issue, int $index) {
                $type = CrawlIssueTypeEnum::from($issue['type']);

                return (new CrawlIssue(['type' => $type, 'severity' => $type->severity(), 'details' => $issue['details']]))->setAttribute('id', "check-$index");
            })
            ->merge($auditPage?->issues->filter(fn (CrawlIssue $issue) => in_array($issue->type, $sitemapTypes, true)) ?? []);

        $page = new CrawlPage(['url' => $check['url'], 'status_code' => $check['status_code'], 'response_ms' => $check['response_ms']]);
        $page->setRelation('crawl', new Crawl(['end_at' => $checkedAt]));
        $page->setRelation('issues', new EloquentCollection($issues->values()->all()));

        return $page;
    }

    /**
     * @throws ValidationException
     */
    public function handle(Webpage $webpage): array
    {
        $url = $this->pageUrl($webpage);

        try {
            $response = Http::withHeaders(['User-Agent' => AuditWebsite::USER_AGENT])
                ->withOptions(['allow_redirects' => ['max' => 5]])
                ->when(!app()->isProduction(), fn ($request) => $request->withoutVerifying())
                ->connectTimeout(10)
                ->timeout(30)
                ->get($url);
            $url = (string)($response->transferStats?->getEffectiveUri() ?? $url);
        } catch (Throwable $exception) {
            $response = $exception;
        }

        $html           = $response instanceof Response && $response->successful() && str_contains(strtolower((string)$response->header('Content-Type')), 'html') ? $response->body() : null;
        $hrefs          = $html === null ? null : AuditedHtml::parse($html)->hrefs;
        $structuredData = $html === null ? null : $this->structuredData($url, $html);
        $issues         = $this->issues($url, $response);

        if ($structuredData && !$structuredData['is_valid']) {
            $issues[] = ['type' => CrawlIssueTypeEnum::INVALID_STRUCTURED_DATA->value, 'details' => ['errors' => RichResultsTest::errors($structuredData)]];
        }

        $check = [
            'checked_at'               => now()->toIso8601String(),
            'url'                      => $url,
            'status_code'              => $response instanceof Response ? $response->status() : null,
            'response_ms'              => $response instanceof Response && $response->transferStats?->getTransferTime() !== null ? (int)round($response->transferStats->getTransferTime() * 1000) : null,
            'fetch_error'              => $response instanceof Throwable ? Str::limit($response->getMessage(), 500) : null,
            'issues'                   => $issues,
            'is_allowed_by_robots_txt' => AuditWebsite::fetchRobotsTxt($this->siteUrl($url))->isAllowed(parse_url($url, PHP_URL_PATH) ?: '/'),
            'internal_links'           => $hrefs === null ? null : $this->internalLinks($url, $hrefs),
            'is_content_in_html'       => $html === null ? null : $this->isContentInHtml($html, $webpage->model?->description),
            'structured_data'          => $structuredData,
            'broken_links'             => $hrefs === null ? null : $this->stillBrokenLinks($webpage, $url, $hrefs),
        ];

        Cache::put(self::cacheKey($webpage), $check, now()->addDays(30));

        return $check;
    }

    /**
     * The same page rules as DetectCrawlIssues, for the issues one page can show without the rest
     * of the website.
     *
     * @return array<int, array{type: string, details: ?array}>
     */
    private function issues(string $url, Response|Throwable $response): array
    {
        $issues = [];
        $add    = function (CrawlIssueTypeEnum $type, ?array $details = null) use (&$issues) {
            $issues[] = ['type' => $type->value, 'details' => $details];
        };

        if (!$response instanceof Response) {
            $add(CrawlIssueTypeEnum::FETCH_FAILED, ['error' => Str::limit($response->getMessage(), 500)]);

            return $issues;
        }

        if ($response->status() >= 500) {
            $add(CrawlIssueTypeEnum::HTTP_5XX, ['status_code' => $response->status()]);
        } elseif ($response->status() >= 400) {
            $add(CrawlIssueTypeEnum::HTTP_4XX, ['status_code' => $response->status()]);
        }

        $responseMs = $response->transferStats?->getTransferTime() !== null ? (int)round($response->transferStats->getTransferTime() * 1000) : null;
        if ($responseMs !== null && $responseMs > CrawlIssueTypeEnum::SLOW_RESPONSE_MS) {
            $add(CrawlIssueTypeEnum::SLOW_RESPONSE, ['response_ms' => $responseMs]);
        }

        if (!$response->successful() || !str_contains(strtolower((string)$response->header('Content-Type')), 'html')) {
            return $issues;
        }

        $html      = AuditedHtml::parse($response->body());
        $isNoindex = $html->isNoindex() || str_contains(strtolower((string)$response->header('X-Robots-Tag')), 'noindex');
        $canonical = $this->absoluteUrl($url, $html->canonical);

        if ($html->imagesWithoutAlt > 0) {
            $add(CrawlIssueTypeEnum::IMAGES_WITHOUT_ALT, ['images' => $html->imagesWithoutAlt]);
        }

        if (!$isNoindex && $canonical === null) {
            $add(CrawlIssueTypeEnum::MISSING_CANONICAL);
        } elseif (!$isNoindex && !$this->isSamePage($canonical, $url)) {
            $add(CrawlIssueTypeEnum::CANONICAL_TO_OTHER_PAGE, ['canonical' => $canonical]);
        }

        if ($isNoindex || ($canonical !== null && !$this->isSamePage($canonical, $url))) {
            return $issues;
        }

        $titleLength = mb_strlen((string)$html->title);
        if ($html->title === null) {
            $add(CrawlIssueTypeEnum::MISSING_TITLE);
        } elseif ($titleLength > CrawlIssueTypeEnum::TITLE_MAX_LENGTH) {
            $add(CrawlIssueTypeEnum::TITLE_TOO_LONG, ['title' => $html->title, 'length' => $titleLength]);
        } elseif ($titleLength < CrawlIssueTypeEnum::TITLE_MIN_LENGTH) {
            $add(CrawlIssueTypeEnum::TITLE_TOO_SHORT, ['title' => $html->title, 'length' => $titleLength]);
        }

        $descriptionLength = mb_strlen((string)$html->metaDescription);
        if ($html->metaDescription === null) {
            $add(CrawlIssueTypeEnum::MISSING_META_DESCRIPTION);
        } elseif ($descriptionLength > CrawlIssueTypeEnum::META_DESCRIPTION_MAX_LENGTH) {
            $add(CrawlIssueTypeEnum::META_DESCRIPTION_TOO_LONG, ['length' => $descriptionLength]);
        } elseif ($descriptionLength < CrawlIssueTypeEnum::META_DESCRIPTION_MIN_LENGTH) {
            $add(CrawlIssueTypeEnum::META_DESCRIPTION_TOO_SHORT, ['length' => $descriptionLength]);
        }

        if ($html->h1Count === 0) {
            $add(CrawlIssueTypeEnum::MISSING_H1);
        } elseif ($html->h1Count > 1) {
            $add(CrawlIssueTypeEnum::MULTIPLE_H1, ['h1_count' => $html->h1Count]);
        }

        return $issues;
    }

    /**
     * The page as this environment serves it, like Webpage::getUrl(): the live website in
     * production, canary in staging, and locally ecom.test or ds.test, which serve only the website
     * set in app.local.
     *
     * @throws ValidationException
     */
    private function pageUrl(Webpage $webpage): string
    {
        if (app()->isProduction()) {
            return $webpage->canonical_url ?: $webpage->getUrl();
        }

        if (app()->environment('local')) {
            $localDomain = match ($webpage->shop->type) {
                ShopTypeEnum::DROPSHIPPING            => config('app.local.retina_dropshipping_domain'),
                ShopTypeEnum::B2B, ShopTypeEnum::B2C  => config('app.local.retina_b2b_domain'),
                default                               => config('app.local.retina_fulfilment_domain'),
            };

            if (Str::after($webpage->website->domain, 'www.') !== Str::after((string)$localDomain, 'www.')) {
                throw ValidationException::withMessages([
                    'check' => __('Locally only :domain is served, so a page of :website cannot be checked here.', ['domain' => $localDomain, 'website' => $webpage->website->domain]),
                ]);
            }
        }

        return $webpage->getUrl();
    }

    /**
     * Google reads structured data after running the page's JavaScript. With SSR the JSON-LD is in
     * the HTML already; without it (locally) Iris writes it in the browser, so the page is rendered.
     *
     * @return array{is_valid: bool, unparsable: int, items: array<int, array{type: string, errors: array<int, string>, warnings: array<int, string>}>}
     */
    private function structuredData(string $url, string $html): array
    {
        if (!str_contains($html, 'application/ld+json')) {
            $html = RenderWebpageHtml::run($url) ?? $html;
        }

        return RichResultsTest::run($html);
    }

    private function siteUrl(string $url): string
    {
        $parts = parse_url($url);

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /**
     * @param  array<int, string>  $hrefs
     */
    private function internalLinks(string $url, array $hrefs): int
    {
        $host = Str::after(strtolower((string)parse_url($url, PHP_URL_HOST)), 'www.');

        return collect($hrefs)
            ->reject(fn (string $href) => $href === '' || str_starts_with($href, '#') || preg_match('/^(?!https?:)[a-z][a-z0-9+.-]*:/i', $href))
            ->filter(fn (string $href) => in_array(Str::after(strtolower((string)parse_url($href, PHP_URL_HOST)), 'www.'), ['', $host], true))
            ->count();
    }

    /**
     * Crawlers that do not run JavaScript only read what the server sends, so the description must
     * be in the HTML itself, outside scripts, rather than rendered from the page props.
     * ponytail: matches the first 80 characters of the description, ignoring whitespace and tags.
     */
    private function isContentInHtml(string $html, ?string $description): bool
    {
        $text            = fn (string $value) => mb_strtolower(preg_replace('/\s+/u', '', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5)));
        $descriptionText = mb_substr($text((string)$description), 0, 80);

        return $descriptionText !== '' && str_contains($text(preg_replace('/<(script|style|template)\b.*?<\/\1>/is', '', $html)), $descriptionText);
    }

    /**
     * The links the latest site audit found broken from this page, asked again when the page still
     * links to them, so a fixed link turns green without waiting for the next audit. Finding new
     * broken links needs the whole website, so that stays with the audit. Null before any audit.
     *
     * @param  array<int, string>  $hrefs
     *
     * @return array<int, array{url: string, status_code: ?int}>|null
     */
    private function stillBrokenLinks(Webpage $webpage, string $url, array $hrefs): ?array
    {
        $auditPage = CrawlPage::where('webpage_id', $webpage->id)
            ->whereHas('crawl', fn ($query) => $query->where('type', CrawlTypeEnum::AUDIT)->where('state', CrawlStateEnum::FINISH))
            ->orderByDesc('crawl_id')
            ->first();

        if (!$auditPage) {
            return null;
        }

        $linkedPaths = collect($hrefs)->map(fn (string $href) => $this->pathOf($this->absoluteUrl($url, $href)))->filter()->unique();

        return CrawlIssue::where('crawl_id', $auditPage->crawl_id)
            ->whereIn('type', [CrawlIssueTypeEnum::HTTP_4XX, CrawlIssueTypeEnum::HTTP_5XX, CrawlIssueTypeEnum::FETCH_FAILED])
            ->whereJsonContains('details->linked_from', $auditPage->url)
            ->with('crawlPage')
            ->get()
            ->map(fn (CrawlIssue $issue) => $issue->crawlPage?->url)
            ->filter(fn (?string $brokenUrl) => $brokenUrl && $linkedPaths->contains($this->pathOf($brokenUrl)))
            ->unique()
            ->map(fn (string $brokenUrl) => ['url' => $brokenUrl, 'status_code' => $this->statusOf($this->siteUrl($url).$this->pathOf($brokenUrl))])
            ->filter(fn (array $link) => $link['status_code'] === null || $link['status_code'] >= 400)
            ->values()
            ->all();
    }

    private function pathOf(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $parts = parse_url($url);
        $path  = '/'.trim($parts['path'] ?? '', '/');

        return isset($parts['query']) ? $path.'?'.$parts['query'] : $path;
    }

    private function statusOf(string $url): ?int
    {
        try {
            return Http::withHeaders(['User-Agent' => AuditWebsite::USER_AGENT])
                ->withOptions(['allow_redirects' => ['max' => 5]])
                ->when(!app()->isProduction(), fn ($request) => $request->withoutVerifying())
                ->connectTimeout(10)
                ->timeout(15)
                ->get($url)
                ->status();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Locally the page canonical still names the live domain, so only the path is compared there.
     */
    private function isSamePage(string $canonical, string $url): bool
    {
        $canonicalParts = parse_url($canonical);
        $urlParts       = parse_url($url);
        $isSamePath     = rtrim($canonicalParts['path'] ?? '/', '/') === rtrim($urlParts['path'] ?? '/', '/');

        return app()->isProduction() ? $isSamePath && ($canonicalParts['host'] ?? null) === ($urlParts['host'] ?? null) : $isSamePath;
    }

    private function absoluteUrl(string $pageUrl, ?string $href): ?string
    {
        $href = trim((string)$href);

        if ($href === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $href)) {
            return $href;
        }

        $base = parse_url($pageUrl);

        return $base['scheme'].'://'.$base['host'].'/'.ltrim($href, '/');
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
