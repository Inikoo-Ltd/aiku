<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl;

use App\Actions\Web\Webpage\WithWebpageIdsByPath;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Crawl\CrawlLinkKindEnum;
use App\Enums\Web\Crawl\CrawlLinkPositionEnum;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Web\Crawl;
use App\Models\SysAdmin\User;
use App\Models\Web\CrawlPage;
use App\Models\Web\Website;
use App\Services\SiteAudit\AuditedHtml;
use App\Services\SiteAudit\CustomExtraction;
use App\Services\SiteAudit\RichResultsTest;
use App\Services\SiteAudit\RobotsTxt;
use App\Services\SiteAudit\SerpPixelWidth;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class AuditWebsite implements ShouldBeUnique
{
    use AsAction;
    use WithWebpageIdsByPath;

    public const string USER_AGENT = 'Mozilla/5.0 (compatible; AikuSiteAuditBot/1.0)';

    public const string AUDIT_HEADER = 'X-Aiku-Site-Audit';

    public const int DEFAULT_CONCURRENCY = 2;

    public const int PAGES_PER_JOB = 1000;

    private const int SECONDS_PER_JOB = 900;

    private const int SECONDS_BETWEEN_JOBS = 30;

    private const int SECONDS_WAITING_FOR_BUDGET = 60;

    private const int ABANDONED_AFTER_MINUTES = 60;

    public const int AUDITS_KEPT = 10;

    public const int LINK_AUDITS_KEPT = 3;

    public const string BLOCKED_BY_ROBOTS = 'blocked';

    public const array DEFAULT_CONFIG = [
        'max_depth'            => null,
        'include'              => [],
        'exclude'              => [],
        'respect_robots_txt'   => true,
        'crawl_subdomains'     => false,
        'check_external_links' => true,
        'check_images'         => true,
        'mode'                 => self::MODE_SPIDER,
        'urls'                 => [],
        'custom_search'        => [],
        'extractions'          => [],
    ];

    /** the settings that add checks without changing which pages are crawled or how they are judged */
    private const array ADDITIVE_CONFIG = ['custom_search', 'extractions'];

    public const int CUSTOM_ITEMS_MAX = 10;

    public const string MODE_SPIDER = 'spider';

    public const string MODE_LIST = 'list';

    public const int LIST_MAX_URLS = 5000;

    private const int ANCHOR_MAX_LENGTH = 500;

    private const string TRACKING_PARAMETER_PATTERN = '/(^|&)(utm_[a-z]+|gclid|fbclid|msclkid|mc_cid|mc_eid|_ga)=/i';

    public const int CONCURRENT_FETCH_BUDGET = 8;

    private const int LINKED_FROM_KEPT = 10;

    private const int EXAMPLES_KEPT = 5;

    private const int EXAMPLE_MAX_LENGTH = 300;

    private const int RESOURCES_PER_FETCH = 4;

    private const int MIN_MS_PER_PAGE = 250;

    private const int RESOURCES_KEPT_PER_PAGE = 200;

    public const int SITEMAP_MAX_URLS = 50000;

    public const int SITEMAP_MAX_BYTES = 52428800;

    private const array SECURITY_HEADERS = ['strict-transport-security', 'content-security-policy', 'x-content-type-options', 'x-frame-options', 'referrer-policy'];

    public const array UNCHECKABLE_STATUSES = [401, 403, 405, 429];

    public const array SKIPPED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'pdf', 'zip', 'css', 'js', 'xml', 'txt', 'mp4', 'mp3', 'woff', 'woff2', 'ttf'];

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 1800;

    public int $jobTries = 3;

    public int $jobBackoff = 60;

    private Crawl $crawl;

    private string $host;

    private RobotsTxt $robotsTxt;

    private array $webpageIdsByPath = [];

    /** @var array<int, array{url: string, depth: int, link_depth: int|null}> */
    private array $queue = [];

    /**
     * Sitemap URLs wait until every page reachable by links from the home page has been found, so
     * `link_depth` is the real number of clicks from the home page and a page the links never
     * reach (an orphan) keeps a null one.
     *
     * @var array<int, string>
     */
    private array $sitemapQueue = [];

    /**
     * External links and images, checked once each after the pages.
     *
     * @var array<int, array{url: string, kind: string}>
     */
    private array $resourceQueue = [];

    /** @var array<string, true> */
    private array $resourcesSeen = [];

    /** @var array<string, array{urls: int, bytes: int}> */
    private array $sitemapFiles = [];

    /** @var array<string, true> */
    private array $seen = [];

    /**
     * Pages that failed or answered 5xx, asked once more after every other page, so a deploy or a
     * timeout is not reported as a broken page.
     *
     * @var array<int, array{url: string, depth: int, link_depth: int|null, retry: true}>
     */
    private array $retryQueue = [];

    /** @var array<string, true> */
    private array $retried = [];

    /** @var array<string, int> the number of sitemap files that list each URL */
    private array $inSitemap = [];

    /** @var array<string, int> */
    private array $inlinks = [];

    /** @var array<string, array<int, string>> */
    private array $linkedFrom = [];

    /** @var array<string, string> the URLs of the website that links lead to but robots.txt does not let us crawl */
    private array $blockedByRobots = [];

    /** @var array<int, array<string, mixed>> the links of the pages fetched in this batch, written with them */
    private array $pendingLinks = [];

    /** @var array{max_depth: int|null, include: array<int, string>, exclude: array<int, string>, respect_robots_txt: bool, crawl_subdomains: bool, check_external_links: bool, check_images: bool} */
    private array $config = self::DEFAULT_CONFIG;

    public function getJobUniqueId(Crawl $crawl, int $part = 0, bool $untilFinished = false, int $waits = 0): string
    {
        return $crawl->id.'-'.$part.'-'.$waits;
    }

    /**
     * An audit has no page limit, so it runs in parts: each part checks up to `PAGES_PER_JOB` pages
     * or `SECONDS_PER_JOB`, keeps where it got to in the cache and queues the next part
     * `SECONDS_BETWEEN_JOBS` later, so no worker is held for hours and other jobs get a turn.
     * `$untilFinished` runs every part in this process, for the command without --async.
     * Audits share `CONCURRENT_FETCH_BUDGET` fetches: one that finds the budget taken stays
     * ready and asks again `SECONDS_WAITING_FOR_BUDGET` later, so twenty audits started at
     * once take turns instead of crawling every storefront together.
     */
    public function handle(Crawl $crawl, int $part = 0, bool $untilFinished = false, int $waits = 0): Crawl
    {
        if ($crawl->type !== CrawlTypeEnum::AUDIT) {
            return $crawl;
        }

        $this->crawl  = $crawl;
        $this->config = self::config($crawl);

        if ($part === 0) {
            if ($crawl->state !== CrawlStateEnum::READY) {
                return $crawl;
            }

            if (!$this->claimConcurrency($untilFinished)) {
                $crawl->touch();
                self::dispatch($crawl, 0, false, $waits + 1)->delay(now()->addSeconds(self::SECONDS_WAITING_FOR_BUDGET));

                return $crawl;
            }

            $this->start();
        } else {
            if ($crawl->state !== CrawlStateEnum::RUNNING) {
                return $crawl;
            }

            if (!$this->restoreProgress()) {
                return $this->finish('interrupted');
            }
        }

        do {
            $outcome = $this->crawlQueue();

            if ($outcome !== 'paused') {
                return $this->finish($outcome);
            }

            $part++;
        } while ($untilFinished);

        $this->saveProgress();

        self::dispatch($crawl, $part)->delay(now()->addSeconds(self::SECONDS_BETWEEN_JOBS));

        return $this->crawl;
    }

    public function jobFailed(Throwable $exception, Crawl $crawl, int $part = 0): void
    {
        self::close($crawl, 'failed');
    }

    /**
     * Closes an audit whose part was lost (a deploy, a worker that died): a running part touches
     * the crawl after every batch of pages, links or images and between parts, and a ready audit
     * touches it each time it waits for a fetch slot, so an audit untouched for
     * `ABANDONED_AFTER_MINUTES` has no job left to finish it.
     */
    public static function closeIfAbandoned(Crawl $crawl): bool
    {
        if (!in_array($crawl->state, [CrawlStateEnum::READY, CrawlStateEnum::RUNNING], true) || $crawl->updated_at?->gt(now()->subMinutes(self::ABANDONED_AFTER_MINUTES))) {
            return false;
        }

        if ($crawl->state === CrawlStateEnum::READY) {
            $crawl->update(['state' => CrawlStateEnum::FINISH, 'running' => false, 'finish_reason' => 'interrupted', 'end_at' => now()]);

            return true;
        }

        self::close($crawl, 'interrupted');

        return true;
    }

    /**
     * A closed audit still gets its report: the issues of every page it got to, queued so a
     * request that closes an abandoned audit is not held while 25k pages are checked.
     */
    private static function close(Crawl $crawl, string $finishReason): void
    {
        $crawl->update(['state' => CrawlStateEnum::FINISH, 'running' => false, 'finish_reason' => $finishReason, 'end_at' => now()]);

        ReportSiteAudit::dispatch($crawl, $finishReason);
    }

    public function report(Crawl $crawl, string $finishReason): Crawl
    {
        $this->crawl  = $crawl;
        $this->config = self::config($crawl);
        $this->restoreProgress();

        return $this->finish($finishReason);
    }

    private function start(): void
    {
        $website = $this->crawl->website;

        $this->stopOtherAudits();

        $baseUrl                = self::publicBaseUrl($website);
        $this->host             = $this->hostWithoutWww(parse_url($baseUrl, PHP_URL_HOST) ?? '');
        $this->robotsTxt        = $this->fetchRobotsTxt($baseUrl);
        $this->webpageIdsByPath = $this->webpageIdsByPath($website);

        if ($this->isListMode()) {
            foreach ($this->config['urls'] as $listedUrl) {
                if ($normalisedUrl = $this->normaliseInternalUrl($listedUrl)) {
                    $this->enqueue($normalisedUrl, 0, null, isStartUrl: true);
                }
            }
        } else {
            $this->enqueue($baseUrl.'/', 0, 0, isStartUrl: true);
        }

        foreach ($this->fetchSitemapUrls($baseUrl) as $sitemapUrl => $sitemapCount) {
            $normalisedUrl = $this->normaliseInternalUrl((string) $sitemapUrl);

            if ($normalisedUrl) {
                $hash                   = md5($normalisedUrl);
                $this->inSitemap[$hash] = ($this->inSitemap[$hash] ?? 0) + $sitemapCount;

                if (!$this->isListMode()) {
                    $this->sitemapQueue[] = $normalisedUrl;
                }
            }
        }
    }

    private function finish(string $finishReason): Crawl
    {
        $this->storeInlinks();
        $this->storeBlockedByRobots();

        EnrichCrawlPages::run($this->crawl);

        DetectCrawlIssues::run($this->crawl->refresh(), $this->linkedFrom, $this->sitemapFiles);

        $this->crawl->update([
            'state'         => CrawlStateEnum::FINISH,
            'end_at'        => now(),
            'running'       => false,
            'finish_reason' => $finishReason,
            'urls_found'    => max(count($this->seen), $this->crawl->urls_found),
        ]);

        Cache::forget(self::progressKey($this->crawl));

        if ($finishReason === 'completed' && $this->crawl->is_full_audit) {
            AskWebmastersToFixSiteAuditIssue::closeFixed($this->crawl);
            ReviewSiteAuditPagesWithAi::dispatch($this->crawl);
        }

        $this->pruneOldAudits($this->crawl->website);

        return $this->crawl;
    }

    private static function progressKey(Crawl $crawl): string
    {
        return "site-audit.$crawl->id.progress";
    }

    private function saveProgress(): void
    {
        $this->crawl->touch();

        Cache::put(self::progressKey($this->crawl), gzcompress(serialize([
            'robots_txt'     => $this->robotsTxt,
            'queue'          => $this->queue,
            'sitemap_queue'  => $this->sitemapQueue,
            'resource_queue' => $this->resourceQueue,
            'resources_seen' => $this->resourcesSeen,
            'sitemap_files'  => $this->sitemapFiles,
            'seen'           => $this->seen,
            'retry_queue'    => $this->retryQueue,
            'retried'        => $this->retried,
            'in_sitemap'     => $this->inSitemap,
            'inlinks'        => $this->inlinks,
            'linked_from'    => $this->linkedFrom,
            'blocked'        => $this->blockedByRobots,
        ])), now()->addDay());
    }

    private function restoreProgress(): bool
    {
        $compressed = Cache::get(self::progressKey($this->crawl));
        $progress   = is_string($compressed) ? unserialize(gzuncompress($compressed), ['allowed_classes' => [RobotsTxt::class]]) : null;

        if (!is_array($progress)) {
            return false;
        }

        $website = $this->crawl->website;

        $this->host             = $this->hostWithoutWww(parse_url(self::publicBaseUrl($website), PHP_URL_HOST) ?? '');
        $this->webpageIdsByPath = $this->webpageIdsByPath($website);
        $this->robotsTxt        = $progress['robots_txt'];
        $this->queue            = $progress['queue'];
        $this->sitemapQueue     = $progress['sitemap_queue'] ?? [];
        $this->resourceQueue    = $progress['resource_queue'] ?? [];
        $this->resourcesSeen    = $progress['resources_seen'] ?? [];
        $this->sitemapFiles     = $progress['sitemap_files'] ?? [];
        $this->seen             = $progress['seen'];
        $this->retryQueue       = $progress['retry_queue'] ?? [];
        $this->retried          = $progress['retried'] ?? [];
        $this->inSitemap        = array_map(intval(...), $progress['in_sitemap']);
        $this->inlinks          = $progress['inlinks'];
        $this->linkedFrom       = $progress['linked_from'];
        $this->blockedByRobots  = $progress['blocked'] ?? [];

        return true;
    }

    private function crawlQueue(): string
    {
        $maxPages       = $this->crawl->max_pages;
        $processedPages = $this->crawl->urls_processed;
        $pagesInThisJob = 0;
        $jobStartedAt   = time();

        while ($this->hasWorkLeft()) {
            if ($this->shouldStop()) {
                return 'interrupted';
            }

            if ($pagesInThisJob >= self::PAGES_PER_JOB || time() - $jobStartedAt >= self::SECONDS_PER_JOB) {
                return 'paused';
            }

            if ($this->queue === [] || ($maxPages !== null && $processedPages >= $maxPages)) {
                if ($this->resourceQueue === []) {
                    return 'max_pages_reached';
                }

                $pagesInThisJob += intdiv($this->checkResources(array_splice($this->resourceQueue, 0, max(1, $this->crawl->concurrency) * self::RESOURCES_PER_FETCH)), self::RESOURCES_PER_FETCH);
                $this->crawl->touch();

                continue;
            }

            $batchSize = max(1, $this->crawl->concurrency);

            if ($maxPages !== null) {
                $batchSize = min($batchSize, $maxPages - $processedPages);
            }

            $batch          = array_splice($this->queue, 0, $batchSize);
            $batchStartedAt = microtime(true);

            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (array $item) => $pool->as($item['url'])
                    ->withHeaders(['User-Agent' => self::USER_AGENT, self::AUDIT_HEADER => (string) $this->crawl->id])
                    ->withOptions(['allow_redirects' => false])
                    ->connectTimeout(10)
                    ->timeout(30)
                    ->get($item['url']),
                $batch
            ));

            $rows = [];

            foreach ($batch as $item) {
                $rows[] = $this->pageRow($item['url'], $item['depth'], $item['link_depth'] ?? null, $responses[$item['url']] ?? null);
            }

            CrawlPage::upsert($rows, ['crawl_id', 'url_hash']);
            $this->storePendingLinks();

            $processedPages += count(array_filter($batch, fn (array $item) => !isset($item['retry'])));
            $pagesInThisJob += count($batch);

            $this->crawl->update(['urls_processed' => $processedPages, 'urls_found' => count($this->seen)]);

            $this->pace($batchStartedAt, count($batch));
        }

        return $processedPages ? 'completed' : 'blocked_by_robots';
    }

    /**
     * At most one page every `MIN_MS_PER_PAGE` per fetch slot, so an audit answered from the
     * edge cache does not run as fast as the network allows.
     */
    private function pace(float $batchStartedAt, int $pages): void
    {
        $waitMicroseconds = (int) (($pages * self::MIN_MS_PER_PAGE / 1000 / max(1, $this->crawl->concurrency) - (microtime(true) - $batchStartedAt)) * 1_000_000);

        if ($waitMicroseconds > 0) {
            Sleep::usleep($waitMicroseconds);
        }
    }

    /**
     * Pages first, then the sitemap URLs the links never reached, then the pages that failed asked
     * once more, then the external links and images those pages use.
     */
    private function hasWorkLeft(): bool
    {
        if ($this->queue === [] && $this->sitemapQueue !== []) {
            foreach ($this->sitemapQueue as $sitemapUrl) {
                $this->enqueue($sitemapUrl, 1, null);
            }

            $this->sitemapQueue = [];
        }

        if ($this->queue === [] && $this->retryQueue !== []) {
            $this->queue      = $this->retryQueue;
            $this->retryQueue = [];
        }

        return $this->queue !== [] || $this->resourceQueue !== [];
    }

    /**
     * Asks each external link and image for its headers only, following redirects. A status that
     * means the site refuses bots (401, 403, 405, 429) is asked again with a normal request.
     *
     * @param  array<int, array{url: string, kind: string}>  $resources
     */
    private function checkResources(array $resources): int
    {
        $request = fn (Pool $pool, array $resource, string $method) => $pool->as($resource['url'])
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->withOptions(['allow_redirects' => ['max' => 5]])
            ->connectTimeout(10)
            ->timeout(20)
            ->send($method, $resource['url']);

        $responses = Http::pool(fn (Pool $pool) => array_map(fn (array $resource) => $request($pool, $resource, 'HEAD'), $resources));

        $retry = array_values(array_filter(
            $resources,
            fn (array $resource) => !($responses[$resource['url']] ?? null) instanceof Response || in_array($responses[$resource['url']]->status(), self::UNCHECKABLE_STATUSES, true)
        ));

        if ($retry !== []) {
            $responses = [...$responses, ...Http::pool(fn (Pool $pool) => array_map(fn (array $resource) => $request($pool, $resource, 'GET'), $retry))];
        }

        $now  = now();
        $rows = [];

        foreach ($resources as $resource) {
            $response = $responses[$resource['url']] ?? null;
            $isFetched = $response instanceof Response;

            $rows[] = [
                'crawl_id'     => $this->crawl->id,
                'kind'         => $resource['kind'],
                'url'          => $resource['url'],
                'url_hash'     => md5($resource['url']),
                'status_code'  => $isFetched ? $response->status() : null,
                'bytes'        => $isFetched && is_numeric($response->header('Content-Length')) ? (int) $response->header('Content-Length') : ($isFetched && $response->body() !== '' ? strlen($response->body()) : null),
                'content_type' => $isFetched ? Str::limit((string) $response->header('Content-Type'), 100, '') : null,
                'fetch_error'  => $isFetched ? null : ($response instanceof Throwable ? Str::limit($response->getMessage(), 500) : __('No response')),
                'created_at'   => $now,
            ];
        }

        DB::table('crawl_resources')->insertOrIgnore($rows);

        return count($resources);
    }

    private function pageRow(string $url, int $depth, ?int $linkDepth, mixed $response): array
    {
        $now = now();
        $row = [
            'crawl_id'           => $this->crawl->id,
            'webpage_id'         => $this->matchWebpageId($url, $this->webpageIdsByPath),
            'url'                => $url,
            'url_hash'           => md5($url),
            'depth'              => $depth,
            'link_depth'         => $linkDepth,
            'status_code'        => null,
            'redirect_to'        => null,
            'response_ms'        => null,
            'bytes'              => null,
            'content_type'       => null,
            'title'              => null,
            'meta_description'   => null,
            'canonical'          => null,
            'robots_meta'        => null,
            'h1_count'           => 0,
            'h1'                 => null,
            'images_without_alt' => 0,
            'hreflang'           => null,
            'is_in_sitemap'      => isset($this->inSitemap[md5($url)]),
            'sitemap_count'      => $this->inSitemap[md5($url)] ?? 0,
            'is_indexable'       => false,
            'word_count'         => null,
            'content_hash'       => null,
            'content_simhash'    => null,
            'facts'              => null,
            'fetch_error'        => null,
            'created_at'         => $now,
            'updated_at'         => $now,
        ];

        if ((!$response instanceof Response || $response->serverError()) && !isset($this->retried[$row['url_hash']])) {
            $this->retried[$row['url_hash']] = true;
            $this->retryQueue[]              = ['url' => $url, 'depth' => $depth, 'link_depth' => $linkDepth, 'retry' => true];
        }

        if (!$response instanceof Response) {
            $row['fetch_error'] = $response instanceof Throwable ? Str::limit($response->getMessage(), 500) : __('No response');

            return $row;
        }

        $transferTime = $response->transferStats?->getTransferTime();

        $row['status_code']  = $response->status();
        $row['response_ms']  = $transferTime !== null ? (int) round($transferTime * 1000) : null;
        $row['bytes']        = strlen($response->body());
        $row['content_type'] = Str::limit((string) $response->header('Content-Type'), 100, '');

        if ($response->redirect()) {
            $redirectTo         = $this->resolveUrl($url, $response->header('Location'));
            $internalRedirect   = $this->normaliseInternalUrl($redirectTo);
            $row['redirect_to'] = $internalRedirect ?? $redirectTo;

            if ($internalRedirect) {
                $this->enqueue($internalRedirect, $depth, $linkDepth);
            }

            return $row;
        }

        if (!$response->successful() || !str_contains(strtolower($row['content_type']), 'html')) {
            return $row;
        }

        $html = AuditedHtml::parse($response->body());

        $canonical = $html->canonical ? $this->resolveUrl($url, $html->canonical) : null;
        $canonical = $this->normaliseInternalUrl($canonical) ?? $canonical;
        $isNoindex = $html->isNoindex() || str_contains(strtolower((string) $response->header('X-Robots-Tag')), 'noindex');

        $row['title']              = $html->title;
        $row['meta_description']   = $html->metaDescription;
        $row['canonical']          = $canonical;
        $row['robots_meta']        = $html->robotsMeta ? Str::limit($html->robotsMeta, 250, '') : null;
        $row['h1_count']           = $html->h1Count;
        $row['h1']                 = $html->h1;
        $row['images_without_alt'] = $html->imagesWithoutAlt;
        $row['is_indexable']       = !$isNoindex && ($canonical === null || $canonical === $url);
        $row['word_count']         = $html->wordCount;
        $row['content_hash']       = $html->contentHash;
        $row['content_simhash']    = $html->contentSimhash;

        if ($html->hreflangs) {
            $row['hreflang'] = json_encode(array_map(fn (array $alternate) => [
                'hreflang' => $alternate['hreflang'],
                'href'     => $this->normaliseInternalUrl($this->resolveUrl($url, $alternate['href'])) ?? $this->resolveUrl($url, $alternate['href']) ?? $alternate['href'],
            ], $html->hreflangs));
        }

        foreach ($html->hrefs as $href) {
            $linkedUrl = $this->normaliseInternalUrl($this->resolveUrl($url, $href));

            if (!$linkedUrl || $linkedUrl === $url) {
                continue;
            }

            $hash                 = md5($linkedUrl);
            $this->inlinks[$hash] = ($this->inlinks[$hash] ?? 0) + 1;

            if (count($this->linkedFrom[$hash] ?? []) < self::LINKED_FROM_KEPT) {
                $this->linkedFrom[$hash][] = $url;
            }

            if (!$this->isListMode()) {
                $this->enqueue($linkedUrl, $depth + 1, $linkDepth === null ? null : $linkDepth + 1);
            }
        }

        $facts = $this->pageFacts($url, $html, $response);

        $facts['structured_data'] = $this->structuredDataFacts($response->body());

        if ($this->config['custom_search'] !== [] || $this->config['extractions'] !== []) {
            $facts['custom'] = CustomExtraction::run($response->body(), $this->config['custom_search'], $this->config['extractions']);
        }

        $row['facts'] = json_encode($facts);

        $this->collectLinks($url, $html, $canonical);

        return $row;
    }

    /**
     * Every link, image, canonical, hreflang and pagination URL on the page, once per target and
     * kind, for the inlinks and outlinks of each URL.
     */
    private function collectLinks(string $url, AuditedHtml $html, ?string $canonical): void
    {
        $fromHash = md5($url);
        $links    = [];

        $add = function (?string $target, CrawlLinkKindEnum $kind, ?string $anchor, string $position, bool $isNofollow) use (&$links, $fromHash) {
            if ($target === null) {
                return;
            }

            $internalUrl = $this->normaliseInternalUrl($target);
            $toUrl       = $internalUrl ?? $target;
            $key         = $kind->value.'|'.$toUrl;

            if (isset($links[$key])) {
                return;
            }

            $links[$key] = [
                'crawl_id'      => $this->crawl->id,
                'from_url_hash' => $fromHash,
                'to_url'        => $toUrl,
                'to_url_hash'   => md5($toUrl),
                'kind'          => $kind->value,
                'anchor'        => $anchor === null || $anchor === '' ? null : Str::limit($anchor, self::ANCHOR_MAX_LENGTH),
                'position'      => $position,
                'is_nofollow'   => $isNofollow,
                'is_internal'   => $this->isInternalHost(strtolower((string) parse_url($toUrl, PHP_URL_HOST))),
            ];
        };

        foreach ($html->anchors as $anchor) {
            $add($this->resolveUrl($url, $anchor['href']), CrawlLinkKindEnum::HYPERLINK, $anchor['text'], $anchor['position'], $anchor['nofollow']);
        }

        foreach ($html->images as $image) {
            $add($this->resolveUrl($url, $image['src']), CrawlLinkKindEnum::IMAGE, $image['alt'], $image['position'], false);
        }

        $add($canonical, CrawlLinkKindEnum::CANONICAL, null, CrawlLinkPositionEnum::HEAD->value, false);

        foreach ($html->hreflangs as $alternate) {
            $add($this->resolveUrl($url, $alternate['href']), CrawlLinkKindEnum::HREFLANG, $alternate['hreflang'], CrawlLinkPositionEnum::HEAD->value, false);
        }

        $add($html->paginationNext ? $this->resolveUrl($url, $html->paginationNext) : null, CrawlLinkKindEnum::NEXT, null, CrawlLinkPositionEnum::HEAD->value, false);
        $add($html->paginationPrev ? $this->resolveUrl($url, $html->paginationPrev) : null, CrawlLinkKindEnum::PREV, null, CrawlLinkPositionEnum::HEAD->value, false);

        array_push($this->pendingLinks, ...array_values($links));
    }

    /**
     * What the page's JSON-LD declares and what Google's rich results would reject in it, from the
     * same checks the webpage check runs.
     *
     * @return array{types: array<int, string>, errors: array<int, string>, warnings: array<int, string>, unparsable: int}
     */
    private function structuredDataFacts(string $body): array
    {
        $result = RichResultsTest::run($body);

        return [
            'types'      => array_values(array_unique(array_column($result['items'], 'type'))),
            'errors'     => array_slice(array_values(array_unique([
                ...($result['unparsable'] > 0 ? ["Unparsable structured data in {$result['unparsable']} block(s)"] : []),
                ...array_merge(...array_column($result['items'], 'errors') ?: [[]]),
            ])), 0, self::EXAMPLES_KEPT * 2),
            'warnings'   => array_slice(array_values(array_unique(array_merge(...array_column($result['items'], 'warnings') ?: [[]]))), 0, self::EXAMPLES_KEPT * 2),
            'unparsable' => $result['unparsable'],
        ];
    }

    /**
     * Writes the links of the pages just fetched, replacing any a page already had in this audit,
     * so a page fetched again (a retry, a part run again) does not count its links twice.
     */
    private function storePendingLinks(): void
    {
        $fromHashes = array_values(array_unique(array_column($this->pendingLinks, 'from_url_hash')));

        if ($fromHashes !== []) {
            DB::table('crawl_links')->where('crawl_id', $this->crawl->id)->whereIn('from_url_hash', $fromHashes)->delete();
        }

        foreach (array_chunk($this->pendingLinks, 1000) as $chunk) {
            DB::table('crawl_links')->insert($chunk);
        }

        $this->pendingLinks = [];
    }

    /**
     * What the issue checks need from a page besides its columns. Lists keep a few examples, and
     * the external links and images are queued to be checked after the pages.
     */
    private function pageFacts(string $url, AuditedHtml $html, Response $response): array
    {
        $isHttps            = str_starts_with($url, 'https://');
        $internalOutlinks   = [];
        $nofollowInternal   = [];
        $emptyAnchors       = 0;
        $nonDescriptive     = [];
        $localhostLinks     = [];
        $unsafeBlankLinks   = [];
        $httpLinks          = [];
        $parameterLinks     = [];
        $externalLinks      = [];
        $trackingLinks      = [];

        foreach ($html->anchors as $anchor) {
            $linkedUrl = $this->resolveUrl($url, $anchor['href']);

            if ($linkedUrl === null) {
                continue;
            }

            $host = strtolower((string) parse_url($linkedUrl, PHP_URL_HOST));

            if ($host === '') {
                continue;
            }

            if ($anchor['text'] === '') {
                $emptyAnchors++;
            } elseif (AuditedHtml::isNonDescriptiveAnchorText($anchor['text'])) {
                $nonDescriptive[$anchor['text']] = true;
            }

            if ($this->isLocalHost($host)) {
                $localhostLinks[$linkedUrl] = true;

                continue;
            }

            if ($this->isInternalHost($host)) {
                $internalOutlinks[$linkedUrl] = true;

                if ($anchor['nofollow']) {
                    $nofollowInternal[$linkedUrl] = true;
                }

                if ($isHttps && str_starts_with(strtolower($linkedUrl), 'http://')) {
                    $httpLinks[$linkedUrl] = true;
                }

                if (parse_url($linkedUrl, PHP_URL_QUERY)) {
                    $parameterLinks[$linkedUrl] = true;

                    if (preg_match(self::TRACKING_PARAMETER_PATTERN, (string) parse_url($linkedUrl, PHP_URL_QUERY))) {
                        $trackingLinks[$linkedUrl] = true;
                    }
                }

                continue;
            }

            if ($anchor['unsafe_blank']) {
                $unsafeBlankLinks[$linkedUrl] = true;
            }

            if (in_array(parse_url($linkedUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
                $externalLinks[$linkedUrl] = true;
            }
        }

        $images = [];

        foreach ($html->images as $image) {
            $imageUrl = $this->resolveUrl($url, $image['src']);

            if ($imageUrl !== null) {
                $images[$imageUrl] = $images[$imageUrl] ?? $image['has_size'];
            }
        }

        $ogImage = isset($html->openGraph['og:image']) ? $this->resolveUrl($url, $html->openGraph['og:image']) : null;

        $externalOutlinks = count($externalLinks);
        $externalLinks    = array_slice(array_keys($externalLinks), 0, self::RESOURCES_KEPT_PER_PAGE);
        $imageUrls     = array_slice(array_keys($images), 0, self::RESOURCES_KEPT_PER_PAGE);

        foreach ($externalLinks as $externalLink) {
            $this->enqueueResource($externalLink, 'link');
        }

        foreach ([...$imageUrls, ...array_filter([$ogImage])] as $imageUrl) {
            $this->enqueueResource($imageUrl, 'image');
        }

        $insecureResources = $isHttps ? array_values(array_filter(
            array_map(fn (string $resource) => $this->resolveUrl($url, $resource), [...$html->resourceUrls, ...$imageUrls]),
            fn (?string $resource) => $resource !== null && str_starts_with(strtolower($resource), 'http://')
        )) : [];

        $insecureForms = array_values(array_filter(
            array_map(fn (string $action) => $this->resolveUrl($url, $action), $html->formActions),
            fn (?string $action) => $action !== null && str_starts_with(strtolower($action), 'http://')
        ));

        $canonicals = array_values(array_unique(array_filter(array_map(
            fn (string $canonical) => $this->normaliseInternalUrl($this->resolveUrl($url, $canonical)) ?? $this->resolveUrl($url, $canonical),
            $html->canonicals
        ))));

        $pagination = array_filter([
            'next' => $html->paginationNext ? $this->resolveUrl($url, $html->paginationNext) : null,
            'prev' => $html->paginationPrev ? $this->resolveUrl($url, $html->paginationPrev) : null,
        ]);

        return array_filter([
            'h2_count'             => $html->h2Count,
            'h2'                   => $html->h2s,
            'headers'              => $this->storedHeaders($response),
            'title_count'          => $html->titleCount,
            'meta_description_count' => $html->metaDescriptionCount,
            'canonicals'           => count($canonicals) > 1 ? $canonicals : null,
            'head_tags_in_body'    => $html->headTagsInBody,
            'is_nofollow'          => $html->isNofollowPage() || preg_match('/\b(nofollow|none)\b/i', (string) $response->header('X-Robots-Tag')) === 1,
            'lorem_ipsum'          => $html->hasLoremIpsum,
            'soft_404'             => $html->soft404Phrase,
            'ssr_fallback'         => $response->header('X-Aiku-Ssr-Fallback') === '1',
            'internal_outlinks'    => count($internalOutlinks),
            'nofollow_internal'    => $this->examples(array_keys($nofollowInternal)),
            'empty_anchors'        => $emptyAnchors,
            'non_descriptive'      => $this->examples(array_keys($nonDescriptive)),
            'localhost_links'      => $this->examples(array_keys($localhostLinks)),
            'unsafe_blank_links'   => $this->examples(array_keys($unsafeBlankLinks)),
            'http_links'           => $this->examples(array_keys($httpLinks)),
            'parameter_links'      => $this->examples(array_keys($parameterLinks)),
            'external_links'       => $externalLinks,
            'images'               => $imageUrls,
            'images_without_size'  => count(array_filter($images, fn (bool $hasSize) => !$hasSize)),
            'og_image'             => $ogImage,
            'og_missing'           => array_values(array_diff(['og:title', 'og:description', 'og:image', 'og:url'], array_keys($html->openGraph))),
            'has_open_graph'       => $html->openGraph !== [],
            'has_twitter_card'     => $html->hasTwitterCard,
            'insecure_resources'   => $this->examples($insecureResources),
            'insecure_forms'       => $this->examples($insecureForms),
            'missing_headers'      => array_values(array_filter(self::SECURITY_HEADERS, fn (string $header) => !$this->hasSecurityHeader($response, $header))),
            'pagination'           => $pagination,
            'pagination_unlinked'  => array_values(array_filter($pagination, fn (string $paginationUrl) => !isset($internalOutlinks[$paginationUrl]))),
            ...$this->validationFacts($url, $html, $response),
            'external_outlinks'    => $externalOutlinks,
            'tracking_links'       => $this->examples(array_keys($trackingLinks)),
            'long_alt_texts'       => $this->examples(array_values(array_filter(array_map(fn (array $image) => $image['alt'], $html->images), fn (?string $alt) => $alt !== null && mb_strlen($alt) > CrawlIssueTypeEnum::ALT_TEXT_MAX_LENGTH))),
        ], fn ($value) => $value !== null && $value !== [] && $value !== false && $value !== 0);
    }

    /**
     * The facts of the checks on how the page is written: headings, directives, refreshes, the
     * document structure and the social tags, and the width of the title and description in results.
     */
    private function validationFacts(string $url, AuditedHtml $html, Response $response): array
    {
        $directives = strtolower(implode(',', array_filter([$html->robotsMeta, $response->header('X-Robots-Tag')])));
        $levels     = $html->headingLevels;
        $skips      = [];

        foreach ($levels as $index => $level) {
            if ($index > 0 && $level > $levels[$index - 1] + 1) {
                $skips['h'.$levels[$index - 1].' > h'.$level] = true;
            }
        }

        $ogImage     = $html->openGraph['og:image'] ?? null;
        $contentType = strtolower((string) $response->header('Content-Type'));
        $isEnglish   = $html->lang !== null && str_starts_with(strtolower($html->lang), 'en');

        return [
            'title_px'              => SerpPixelWidth::of($html->title, SerpPixelWidth::TITLE_FONT_SIZE),
            'meta_description_px'   => SerpPixelWidth::of($html->metaDescription, SerpPixelWidth::DESCRIPTION_FONT_SIZE),
            'lang'                  => $html->lang,
            'h1_not_first'          => $html->h1Count > 0 && $levels !== [] && $levels[0] !== 1,
            'heading_skips'         => array_keys($skips),
            'alt_in_h1'             => $html->hasAltTextInH1,
            'reading_ease'          => $isEnglish ? $html->readingEase : null,
            'canonical_relative'    => $html->canonical !== null && !preg_match('/^https?:\/\//i', $html->canonical),
            'canonical_fragment'    => $html->canonical !== null && str_contains($html->canonical, '#'),
            'nosnippet'             => preg_match('/\b(nosnippet|max-snippet\s*:\s*0)\b/', $directives) === 1,
            'noimageindex'          => str_contains($directives, 'noimageindex'),
            'unavailable_after'     => str_contains($directives, 'unavailable_after'),
            'meta_refresh'          => $html->metaRefresh,
            'http_refresh'          => $response->header('Refresh') !== '' ? $response->header('Refresh') : null,
            'broken_bookmarks'      => $this->examples($html->brokenBookmarks),
            'javascript_links'      => $html->javascriptLinks,
            'protocol_relative'     => $this->examples($html->protocolRelativeUrls),
            'missing_doctype'       => !$html->hasDoctype,
            'missing_head'          => $html->headCount === 0,
            'multiple_heads'        => $html->headCount > 1 ? $html->headCount : null,
            'missing_body'          => $html->bodyCount === 0,
            'multiple_bodies'       => $html->bodyCount > 1 ? $html->bodyCount : null,
            'invalid_head_elements' => $html->invalidHeadElements,
            'missing_viewport'      => !$html->hasViewport,
            'missing_charset'       => !$html->hasCharset && !str_contains($contentType, 'charset='),
            'social_multiple'       => array_keys(array_filter($html->socialTagCounts, fn (int $count) => $count > 1)),
            'og_image_relative'     => $ogImage !== null && !preg_match('/^https?:\/\//i', $ogImage),
            'og_title'              => $html->openGraph['og:title'] ?? null,
            'og_description'        => $html->openGraph['og:description'] ?? null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function storedHeaders(Response $response): array
    {
        $headers = [];

        foreach ($response->headers() as $name => $values) {
            $headers[strtolower($name)] = Str::limit(implode(', ', (array) $values), self::EXAMPLE_MAX_LENGTH);
        }

        ksort($headers);

        return $headers;
    }

    private function hasSecurityHeader(Response $response, string $header): bool
    {
        if ($header === 'x-frame-options' && str_contains(strtolower((string) $response->header('Content-Security-Policy')), 'frame-ancestors')) {
            return true;
        }

        return $response->header($header) !== '';
    }

    /**
     * @param  array<int, string>  $values
     *
     * @return array{count: int, examples: array<int, string>}|null
     */
    private function examples(array $values): ?array
    {
        $values = array_values(array_unique($values));

        return $values === [] ? null : [
            'count'    => count($values),
            'examples' => array_map(fn (string $value) => Str::limit($value, self::EXAMPLE_MAX_LENGTH), array_slice($values, 0, self::EXAMPLES_KEPT)),
        ];
    }

    private function isLocalHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '[::1]'], true) || preg_match('/\.(test|local|localhost)$/', $host) === 1;
    }

    private function enqueue(string $url, int $depth, ?int $linkDepth, bool $isStartUrl = false): void
    {
        $hash = md5($url);

        if (isset($this->seen[$hash])) {
            return;
        }

        $this->seen[$hash] = true;

        if ($this->config['respect_robots_txt'] && !$this->robotsTxt->isAllowed(parse_url($url, PHP_URL_PATH) ?: '/')) {
            $this->blockedByRobots[$hash] = $url;

            return;
        }

        if (!$isStartUrl && !$this->isWithinScope($url, $depth)) {
            return;
        }

        $this->queue[] = ['url' => $url, 'depth' => $depth, 'link_depth' => $linkDepth];
    }

    private function isWithinScope(string $url, int $depth): bool
    {
        if ($this->config['max_depth'] !== null && $depth > $this->config['max_depth']) {
            return false;
        }

        foreach ($this->config['exclude'] as $pattern) {
            if (self::matchesPattern($pattern, $url)) {
                return false;
            }
        }

        if ($this->config['include'] === []) {
            return true;
        }

        foreach ($this->config['include'] as $pattern) {
            if (self::matchesPattern($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    public static function matchesPattern(string $pattern, string $url): bool
    {
        return @preg_match('~'.str_replace('~', '\\~', $pattern).'~i', $url) === 1;
    }

    public static function isValidPattern(string $pattern): bool
    {
        return @preg_match('~'.str_replace('~', '\\~', $pattern).'~i', '') !== false;
    }

    /**
     * @return array{max_depth: int|null, include: array<int, string>, exclude: array<int, string>, respect_robots_txt: bool, crawl_subdomains: bool, check_external_links: bool, check_images: bool}
     */
    public static function config(Crawl $crawl): array
    {
        return array_replace(self::DEFAULT_CONFIG, array_intersect_key($crawl->config ?? [], self::DEFAULT_CONFIG));
    }

    /**
     * A list crawl checks only the URLs it was given, and the redirects they lead to, without
     * following their links, to look again at a set of pages after fixing them.
     */
    private function isListMode(): bool
    {
        return $this->config['mode'] === self::MODE_LIST;
    }

    /**
     * Whether a URL belongs to the website, the only URLs a list crawl can check.
     */
    public static function isWebsiteUrl(Website $website, string $url): bool
    {
        $host        = Str::after(strtolower((string) parse_url($url, PHP_URL_HOST)), 'www.');
        $websiteHost = Str::after(strtolower((string) parse_url(self::publicBaseUrl($website), PHP_URL_HOST)), 'www.');

        return $host !== '' && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) && $host === $websiteHost;
    }

    private function isInternalHost(string $host): bool
    {
        $host = $this->hostWithoutWww($host);

        return $host === $this->host || ($this->config['crawl_subdomains'] && str_ends_with($host, '.'.$this->host));
    }

    private function enqueueResource(string $url, string $kind): void
    {
        $hash = md5($url);

        if (isset($this->resourcesSeen[$hash]) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return;
        }

        if (($kind === 'link' && !$this->config['check_external_links']) || ($kind === 'image' && !$this->config['check_images'])) {
            return;
        }

        $this->resourcesSeen[$hash] = true;
        $this->resourceQueue[]      = ['url' => $url, 'kind' => $kind];
    }

    private function normaliseInternalUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $parts = parse_url($url);

        if (!$parts || !isset($parts['host']) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return null;
        }

        if (!$this->isInternalHost(strtolower($parts['host'])) || isset($parts['query'])) {
            return null;
        }

        $path      = $parts['path'] ?? '/';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, self::SKIPPED_EXTENSIONS, true)) {
            return null;
        }

        return strtolower($parts['scheme']).'://'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '').$path;
    }

    private function resolveUrl(string $baseUrl, ?string $href): ?string
    {
        $href = trim((string) $href);

        if ($href === '' || str_starts_with($href, '#') || preg_match('/^(mailto|tel|javascript|data|sms|whatsapp):/i', $href)) {
            return null;
        }

        $href = preg_replace('/#.*$/', '', $href);

        if (preg_match('/^https?:\/\//i', $href)) {
            return $href;
        }

        $base = parse_url($baseUrl);
        $root = $base['scheme'].'://'.$base['host'].(isset($base['port']) ? ':'.$base['port'] : '');

        if (str_starts_with($href, '//')) {
            return $base['scheme'].':'.$href;
        }

        if (str_starts_with($href, '/')) {
            return $root.$href;
        }

        $directory = preg_replace('/\/[^\/]*$/', '/', $base['path'] ?? '/');
        $segments  = [];

        foreach (explode('/', $directory.$href) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '.') {
                $segments[] = $segment;
            }
        }

        return $root.'/'.ltrim(implode('/', $segments), '/');
    }

    public static function publicBaseUrl(Website $website): string
    {
        $storefrontUrl = parse_url((string) $website->storefront?->canonical_url);

        if (isset($storefrontUrl['scheme'], $storefrontUrl['host'])) {
            return $storefrontUrl['scheme'].'://'.$storefrontUrl['host'];
        }

        return 'https://'.$website->domain;
    }

    private function hostWithoutWww(string $host): string
    {
        return Str::after(strtolower($host), 'www.');
    }

    private function fetchRobotsTxt(string $baseUrl): RobotsTxt
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->connectTimeout(10)->timeout(20)->get($baseUrl.'/robots.txt');
        } catch (Throwable) {
            return RobotsTxt::allowAll();
        }

        return $response->successful() ? RobotsTxt::parse($response->body(), 'AikuSiteAuditBot') : RobotsTxt::allowAll();
    }

    /**
     * Every page URL the sitemaps list, with the number of sitemap files listing it. The size of
     * each sitemap file is kept for the check against Google's limits.
     *
     * @return array<string, int>
     */
    private function fetchSitemapUrls(string $baseUrl): array
    {
        $sitemapsToRead = $this->robotsTxt->sitemaps() ?: [$baseUrl.'/sitemap.xml'];
        $sitemapsRead   = [];
        $pageUrls       = [];

        while ($sitemapsToRead !== [] && count($sitemapsRead) < 100) {
            $sitemapUrl = array_shift($sitemapsToRead);

            if (isset($sitemapsRead[$sitemapUrl])) {
                continue;
            }

            $sitemapsRead[$sitemapUrl] = true;

            try {
                $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->connectTimeout(10)->timeout(60)->get($sitemapUrl);
            } catch (Throwable) {
                continue;
            }

            if (!$response->successful()) {
                continue;
            }

            $body = $response->body();
            preg_match_all('/<loc>\s*(.*?)\s*<\/loc>/is', $body, $matches);
            $locations = array_map(fn (string $location) => html_entity_decode($location, ENT_QUOTES | ENT_XML1), $matches[1]);

            if (str_contains($body, '<sitemapindex')) {
                array_push($sitemapsToRead, ...$locations);

                continue;
            }

            $this->sitemapFiles[$sitemapUrl] = ['urls' => count($locations), 'bytes' => strlen($body)];

            foreach (array_unique($locations) as $location) {
                $pageUrls[$location] = ($pageUrls[$location] ?? 0) + 1;
            }
        }

        return $pageUrls;
    }

    /**
     * The URLs robots.txt keeps us from crawling, as resources of their own kind, so the crawler can
     * list them once with the pages that link to them, as search engines see them.
     */
    private function storeBlockedByRobots(): void
    {
        $now = now();

        foreach (array_chunk($this->blockedByRobots, 1000, true) as $chunk) {
            DB::table('crawl_resources')->insertOrIgnore(array_map(fn (string $url, string $hash) => [
                'crawl_id'    => $this->crawl->id,
                'kind'        => self::BLOCKED_BY_ROBOTS,
                'url'         => $url,
                'url_hash'    => $hash,
                'fetch_error' => 'Blocked by robots.txt',
                'created_at'  => $now,
            ], $chunk, array_keys($chunk)));
        }
    }

    private function storeInlinks(): void
    {
        foreach (array_chunk($this->inlinks, 1000, true) as $chunk) {
            $values   = [];
            $bindings = [];

            foreach ($chunk as $hash => $count) {
                $values[]   = '(?, ?::integer)';
                $bindings[] = $hash;
                $bindings[] = $count;
            }

            DB::update(
                'UPDATE crawl_pages SET inlinks = links.inlinks FROM (VALUES '.implode(', ', $values).') AS links(url_hash, inlinks)
                WHERE crawl_pages.crawl_id = ? AND crawl_pages.url_hash = links.url_hash',
                [...$bindings, $this->crawl->id]
            );
        }
    }

    private function stopOtherAudits(): void
    {
        Crawl::where('website_id', $this->crawl->website_id)
            ->where('type', CrawlTypeEnum::AUDIT)
            ->where('id', '!=', $this->crawl->id)
            ->where('state', '!=', CrawlStateEnum::FINISH)
            ->get()
            ->each(fn (Crawl $crawl) => self::closeIfAbandoned($crawl) || StopCrawl::run($crawl));
    }

    private function claimConcurrency(bool $evenWithoutBudget): bool
    {
        try {
            return $this->claimConcurrencyUnderLock($evenWithoutBudget);
        } catch (LockTimeoutException) {
            return false;
        }
    }

    private function claimConcurrencyUnderLock(bool $evenWithoutBudget): bool
    {
        return Cache::lock('audit-website-concurrency', 10)->block(10, function () use ($evenWithoutBudget) {
            $concurrencyInUse = (int) Crawl::where('running', true)->where('id', '!=', $this->crawl->id)->sum('concurrency');
            $available        = self::CONCURRENT_FETCH_BUDGET - $concurrencyInUse;

            if ($available < 1 && !$evenWithoutBudget) {
                return false;
            }

            $this->crawl->update([
                'concurrency' => max(1, min($available, $this->crawl->concurrency)),
                'state'       => CrawlStateEnum::RUNNING,
                'start_at'    => now(),
                'running'     => true,
            ]);

            return true;
        });
    }

    private function shouldStop(): bool
    {
        return (bool) DB::table('crawls')->where('id', $this->crawl->id)->value('should_stop');
    }

    /**
     * Keeps the latest `AUDITS_KEPT` full audits and as many limited crawls, so crawls started to
     * look at one part of the website never push the full audits behind the health history out.
     */
    private function pruneOldAudits(Website $website): void
    {
        $keptIds = Crawl::where('website_id', $website->id)
            ->where('type', CrawlTypeEnum::AUDIT)
            ->where('is_full_audit', true)
            ->orderByDesc('id')
            ->limit(self::AUDITS_KEPT)
            ->pluck('id')
            ->merge(Crawl::where('website_id', $website->id)
                ->where('type', CrawlTypeEnum::AUDIT)
                ->where('is_full_audit', false)
                ->orderByDesc('id')
                ->limit(self::AUDITS_KEPT)
                ->pluck('id'))
            ->sortDesc()
            ->values();

        Crawl::where('website_id', $website->id)
            ->where('type', CrawlTypeEnum::AUDIT)
            ->whereNotIn('id', $keptIds)
            ->where('running', false)
            ->delete();

        $crawlIdsWithoutLinks = Crawl::where('website_id', $website->id)
            ->where('type', CrawlTypeEnum::AUDIT)
            ->where('running', false)
            ->whereNotIn('id', $keptIds->take(self::LINK_AUDITS_KEPT))
            ->pluck('id');

        DB::table('crawl_links')->whereIn('crawl_id', $crawlIdsWithoutLinks)->delete();
    }

    /**
     * Whether a crawl covers the whole website with every check, so what it finds can stand for the
     * website: the health score, the page scores of products and families and the content
     * suggestions read only these. A crawl limited by pages, depth or patterns, or that skips
     * checks, would report every page it left out as fixed and orphan the pages it did not reach.
     */
    public static function isFullAudit(?int $maxPages, ?array $config): bool
    {
        if ($maxPages !== null) {
            return false;
        }

        $scopeDefaults = array_diff_key(self::DEFAULT_CONFIG, array_flip(self::ADDITIVE_CONFIG));

        return $config === null || array_replace($scopeDefaults, array_intersect_key($config, $scopeDefaults)) == $scopeDefaults;
    }

    public static function startFor(Website $website, CrawlTriggerEnum $trigger, ?int $maxPages = null, int $concurrency = self::DEFAULT_CONCURRENCY, ?array $config = null, ?string $name = null, ?User $user = null): Crawl
    {
        /** @var Crawl $crawl */
        $crawl = $website->crawls()->create([
            'type'        => CrawlTypeEnum::AUDIT,
            'state'       => CrawlStateEnum::READY,
            'trigger'     => $trigger,
            'depth'       => 0,
            'concurrency' => $concurrency,
            'max_pages'   => $maxPages,
            'config'        => $config === null ? null : array_replace(self::DEFAULT_CONFIG, array_intersect_key($config, self::DEFAULT_CONFIG)),
            'name'          => $name,
            'user_id'       => $user?->id,
            'is_full_audit' => self::isFullAudit($maxPages, $config),
        ]);

        return $crawl;
    }

    public function getCommandSignature(): string
    {
        return 'crawl:audit {website? : Website slug} {--max-pages= : Stop after this many pages} {--c|concurrency=2} {--a|async : Run asynchronously}';
    }

    public function asCommand(Command $command): int
    {
        $websites = Website::query()
            ->when(
                $command->argument('website'),
                fn ($query, $slug) => $query->where('slug', $slug),
                fn ($query) => $query->where('state', WebsiteStateEnum::LIVE)
            )
            ->get();

        $maxPages = $command->option('max-pages') ? (int) $command->option('max-pages') : null;

        foreach ($websites as $website) {
            $crawl = self::startFor($website, CrawlTriggerEnum::COMMAND, $maxPages, (int) $command->option('concurrency'));

            if ($command->option('async')) {
                self::dispatch($crawl);

                continue;
            }

            try {
                $crawl = $this->handle($crawl, untilFinished: true);
                $command->line("$website->slug: $crawl->urls_processed pages, health score $crawl->health_score, $crawl->finish_reason");
            } catch (Throwable $e) {
                $crawl->update(['state' => CrawlStateEnum::FINISH, 'running' => false, 'finish_reason' => 'failed', 'end_at' => now()]);
                $command->error("$website->slug: {$e->getMessage()}");
            }
        }

        return 0;
    }
}
