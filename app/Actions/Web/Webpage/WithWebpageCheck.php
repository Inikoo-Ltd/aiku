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
use App\Models\Web\Crawl;
use App\Models\Web\CrawlIssue;
use App\Models\Web\CrawlPage;
use App\Models\Web\Webpage;
use App\Services\SiteAudit\AuditedHtml;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Client\Response;
use Illuminate\Http\RedirectResponse;
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
 * website's latest audit; links and sitemap still come from that audit.
 */
trait WithWebpageCheck
{
    public static function cacheKey(Webpage $webpage): string
    {
        return "webpage-check:$webpage->id";
    }

    /**
     * @return array{checked_at: string, url: string, status_code: ?int, response_ms: ?int, fetch_error: ?string, issues: array<int, array{type: string, details: ?array}>, is_allowed_by_robots_txt?: bool, internal_links?: ?int, is_content_in_html?: ?bool}|null
     */
    public static function cached(Webpage $webpage): ?array
    {
        return Cache::get(self::cacheKey($webpage));
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

        $html = $response instanceof Response && $response->successful() && str_contains(strtolower((string)$response->header('Content-Type')), 'html') ? $response->body() : null;

        $check = [
            'checked_at'               => now()->toIso8601String(),
            'url'                      => $url,
            'status_code'              => $response instanceof Response ? $response->status() : null,
            'response_ms'              => $response instanceof Response && $response->transferStats?->getTransferTime() !== null ? (int)round($response->transferStats->getTransferTime() * 1000) : null,
            'fetch_error'              => $response instanceof Throwable ? Str::limit($response->getMessage(), 500) : null,
            'issues'                   => $this->issues($url, $response),
            'is_allowed_by_robots_txt' => AuditWebsite::fetchRobotsTxt($this->siteUrl($url))->isAllowed(parse_url($url, PHP_URL_PATH) ?: '/'),
            'internal_links'           => $html === null ? null : $this->internalLinks($url, AuditedHtml::parse($html)->hrefs),
            'is_content_in_html'       => $html === null ? null : $this->isContentInHtml($html, $webpage->model?->description),
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
