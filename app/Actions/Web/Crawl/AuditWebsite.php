<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl;

use App\Actions\Web\Webpage\WithWebpageIdsByPath;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Web\Crawl;
use App\Models\Web\CrawlPage;
use App\Models\Web\Website;
use App\Services\SiteAudit\AuditedHtml;
use App\Services\SiteAudit\RobotsTxt;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class AuditWebsite implements ShouldBeUnique
{
    use AsAction;
    use WithWebpageIdsByPath;

    public const string USER_AGENT = 'Mozilla/5.0 (compatible; AikuSiteAuditBot/1.0)';

    public const int DEFAULT_CONCURRENCY = 2;

    public const int PAGES_PER_JOB = 1000;

    private const int SECONDS_PER_JOB = 900;

    private const int SECONDS_BETWEEN_JOBS = 30;

    private const int ABANDONED_AFTER_MINUTES = 60;

    public const int AUDITS_KEPT = 10;

    private const int CONCURRENT_FETCH_BUDGET = 8;

    private const int LINKED_FROM_KEPT = 10;

    public const array SKIPPED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'pdf', 'zip', 'css', 'js', 'xml', 'txt', 'mp4', 'mp3', 'woff', 'woff2', 'ttf'];

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 1800;

    public int $jobTries = 1;

    private Crawl $crawl;

    private string $host;

    private RobotsTxt $robotsTxt;

    private array $webpageIdsByPath = [];

    /** @var array<string, array{url: string, depth: int}> */
    private array $queue = [];

    /** @var array<string, true> */
    private array $seen = [];

    /** @var array<string, true> */
    private array $inSitemap = [];

    /** @var array<string, int> */
    private array $inlinks = [];

    /** @var array<string, array<int, string>> */
    private array $linkedFrom = [];

    public function getJobUniqueId(Crawl $crawl, int $part = 0): string
    {
        return $crawl->id.'-'.$part;
    }

    /**
     * An audit has no page limit, so it runs in parts: each part checks up to `PAGES_PER_JOB` pages
     * or `SECONDS_PER_JOB`, keeps where it got to in the cache and queues the next part
     * `SECONDS_BETWEEN_JOBS` later, so no worker is held for hours and other jobs get a turn.
     * `$untilFinished` runs every part in this process, for the command without --async.
     */
    public function handle(Crawl $crawl, int $part = 0, bool $untilFinished = false): Crawl
    {
        if ($crawl->type !== CrawlTypeEnum::AUDIT) {
            return $crawl;
        }

        $this->crawl = $crawl;

        if ($part === 0) {
            if ($crawl->state !== CrawlStateEnum::READY) {
                return $crawl;
            }

            $this->start();
        } else {
            if ($crawl->state !== CrawlStateEnum::RUNNING) {
                Cache::forget(self::progressKey($crawl));

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
     * Closes an audit whose part was lost (a deploy, a worker that died): a running part writes
     * its progress every few pages, so an audit untouched for `ABANDONED_AFTER_MINUTES` has no
     * job left to finish it.
     */
    public static function closeIfAbandoned(Crawl $crawl): bool
    {
        if ($crawl->state !== CrawlStateEnum::RUNNING || $crawl->updated_at?->gt(now()->subMinutes(self::ABANDONED_AFTER_MINUTES))) {
            return false;
        }

        self::close($crawl, 'interrupted');

        return true;
    }

    private static function close(Crawl $crawl, string $finishReason): void
    {
        $crawl->update(['state' => CrawlStateEnum::FINISH, 'running' => false, 'finish_reason' => $finishReason, 'end_at' => now()]);

        Cache::forget(self::progressKey($crawl));
    }

    private function start(): void
    {
        $website = $this->crawl->website;

        $this->stopOtherAudits();
        $this->claimConcurrency();

        $this->crawl->update(['state' => CrawlStateEnum::RUNNING, 'start_at' => now(), 'running' => true]);

        $baseUrl                = self::publicBaseUrl($website);
        $this->host             = $this->hostWithoutWww(parse_url($baseUrl, PHP_URL_HOST) ?? '');
        $this->robotsTxt        = $this->fetchRobotsTxt($baseUrl);
        $this->webpageIdsByPath = $this->webpageIdsByPath($website);

        $this->enqueue($baseUrl.'/', 0);

        foreach ($this->fetchSitemapUrls($baseUrl) as $sitemapUrl) {
            $normalisedUrl = $this->normaliseInternalUrl($sitemapUrl);

            if ($normalisedUrl) {
                $this->inSitemap[md5($normalisedUrl)] = true;
                $this->enqueue($normalisedUrl, 1);
            }
        }
    }

    private function finish(string $finishReason): Crawl
    {
        $this->storeInlinks();

        DetectCrawlIssues::run($this->crawl->refresh(), $this->linkedFrom);

        $this->crawl->update([
            'state'         => CrawlStateEnum::FINISH,
            'end_at'        => now(),
            'running'       => false,
            'finish_reason' => $finishReason,
            'urls_found'    => max(count($this->seen), $this->crawl->urls_found),
        ]);

        Cache::forget(self::progressKey($this->crawl));

        $this->pruneOldAudits($this->crawl->website);

        return $this->crawl;
    }

    private static function progressKey(Crawl $crawl): string
    {
        return "site-audit.$crawl->id.progress";
    }

    private function saveProgress(): void
    {
        Cache::put(self::progressKey($this->crawl), gzcompress(serialize([
            'robots_txt'  => $this->robotsTxt,
            'queue'       => $this->queue,
            'seen'        => $this->seen,
            'in_sitemap'  => $this->inSitemap,
            'inlinks'     => $this->inlinks,
            'linked_from' => $this->linkedFrom,
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
        $this->seen             = $progress['seen'];
        $this->inSitemap        = $progress['in_sitemap'];
        $this->inlinks          = $progress['inlinks'];
        $this->linkedFrom       = $progress['linked_from'];

        return true;
    }

    private function crawlQueue(): string
    {
        $maxPages       = $this->crawl->max_pages;
        $processedPages = $this->crawl->urls_processed;
        $pagesInThisJob = 0;
        $jobStartedAt   = time();

        while ($this->queue !== []) {
            if ($this->shouldStop()) {
                return 'interrupted';
            }

            if ($maxPages !== null && $processedPages >= $maxPages) {
                return 'max_pages_reached';
            }

            if ($pagesInThisJob >= self::PAGES_PER_JOB || time() - $jobStartedAt >= self::SECONDS_PER_JOB) {
                return 'paused';
            }

            $batchSize = max(1, $this->crawl->concurrency);

            if ($maxPages !== null) {
                $batchSize = min($batchSize, $maxPages - $processedPages);
            }

            $batch = array_splice($this->queue, 0, $batchSize);

            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (array $item) => $pool->as($item['url'])
                    ->withHeaders(['User-Agent' => self::USER_AGENT])
                    ->withOptions(['allow_redirects' => false])
                    ->connectTimeout(10)
                    ->timeout(30)
                    ->get($item['url']),
                $batch
            ));

            $rows = [];

            foreach ($batch as $item) {
                $rows[] = $this->pageRow($item['url'], $item['depth'], $responses[$item['url']] ?? null);
            }

            CrawlPage::insert($rows);

            $processedPages += count($batch);
            $pagesInThisJob += count($batch);

            $this->crawl->update(['urls_processed' => $processedPages, 'urls_found' => count($this->seen)]);
        }

        return 'completed';
    }

    private function pageRow(string $url, int $depth, mixed $response): array
    {
        $now = now();
        $row = [
            'crawl_id'           => $this->crawl->id,
            'webpage_id'         => $this->matchWebpageId($url, $this->webpageIdsByPath),
            'url'                => $url,
            'url_hash'           => md5($url),
            'depth'              => $depth,
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
            'images_without_alt' => 0,
            'hreflang'           => null,
            'is_in_sitemap'      => isset($this->inSitemap[md5($url)]),
            'is_indexable'       => false,
            'fetch_error'        => null,
            'created_at'         => $now,
            'updated_at'         => $now,
        ];

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
                $this->enqueue($internalRedirect, $depth);
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
        $row['images_without_alt'] = $html->imagesWithoutAlt;
        $row['is_indexable']       = !$isNoindex && ($canonical === null || $canonical === $url);

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

            $this->enqueue($linkedUrl, $depth + 1);
        }

        return $row;
    }

    private function enqueue(string $url, int $depth): void
    {
        $hash = md5($url);

        if (isset($this->seen[$hash])) {
            return;
        }

        $this->seen[$hash] = true;

        if (!$this->robotsTxt->isAllowed(parse_url($url, PHP_URL_PATH) ?: '/')) {
            return;
        }

        $this->queue[] = ['url' => $url, 'depth' => $depth];
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

        if ($this->hostWithoutWww(strtolower($parts['host'])) !== $this->host || isset($parts['query'])) {
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
     * @return array<int, string>
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
            } else {
                array_push($pageUrls, ...$locations);
            }
        }

        return array_values(array_unique($pageUrls));
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

    private function claimConcurrency(): void
    {
        $concurrencyInUse = (int) Crawl::where('running', true)->where('id', '!=', $this->crawl->id)->sum('concurrency');
        $available        = self::CONCURRENT_FETCH_BUDGET - $concurrencyInUse;

        $this->crawl->update(['concurrency' => max(1, min($available, $this->crawl->concurrency))]);
    }

    private function shouldStop(): bool
    {
        return (bool) DB::table('crawls')->where('id', $this->crawl->id)->value('should_stop');
    }

    private function pruneOldAudits(Website $website): void
    {
        $keptIds = Crawl::where('website_id', $website->id)
            ->where('type', CrawlTypeEnum::AUDIT)
            ->orderByDesc('id')
            ->limit(self::AUDITS_KEPT)
            ->pluck('id');

        Crawl::where('website_id', $website->id)
            ->where('type', CrawlTypeEnum::AUDIT)
            ->whereNotIn('id', $keptIds)
            ->where('running', false)
            ->delete();
    }

    public static function startFor(Website $website, CrawlTriggerEnum $trigger, ?int $maxPages = null, int $concurrency = self::DEFAULT_CONCURRENCY): Crawl
    {
        /** @var Crawl $crawl */
        $crawl = $website->crawls()->create([
            'type'        => CrawlTypeEnum::AUDIT,
            'state'       => CrawlStateEnum::READY,
            'trigger'     => $trigger,
            'depth'       => 0,
            'concurrency' => $concurrency,
            'max_pages'   => $maxPages,
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
