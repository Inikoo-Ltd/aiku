<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\Web\Webpage\WithWebpageIdsByPath;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Web\SeoBacklink;
use App\Models\Web\SeoBacklinkSummary;
use App\Models\Web\SeoReferringDomain;
use App\Models\Web\Website;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\DataForSeoException;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Weekly backlink data from DataForSEO for every live website and the competitor domains of its
 * shop: a summary and the referring domains per domain. For our own websites, every four weeks, the
 * links themselves: one per referring domain, every broken one, and the new and lost ones since the
 * previous fetch. Each domain is fetched once a day at most, however many shops list it.
 */
class FetchBacklinks
{
    use AsAction;
    use WithWebpageIdsByPath;

    public const int OWN_REFERRING_DOMAINS = 3000;

    public const int COMPETITOR_REFERRING_DOMAINS = 1000;

    public const int BACKLINKS_REFRESH_DAYS = 27;

    private const int ROWS_PER_REQUEST = 1000;

    private const int UPSERT_CHUNK = 500;

    public string $commandSignature = 'seo:fetch_backlinks {website? : Website slug} {--backlinks : Fetch the link lists of our websites even if they are not due}';

    public string $commandDescription = 'Fetch backlink summaries, referring domains and our link lists from DataForSEO';

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 7200;

    public int $jobTries = 1;

    /**
     * @var array<int, string>
     */
    private array $ownDomains = [];

    /**
     * @throws DataForSeoException
     */
    public function handle(?Website $website = null, bool $forceBacklinks = false): int
    {
        $client = DataForSeoClient::make();

        if (!$client) {
            return 0;
        }

        $this->ownDomains = Website::pluck('domain')->map(fn ($domain) => StoreSerpResult::normaliseDomain($domain))->filter()->unique()->values()->all();

        $websites = Website::query()
            ->where('state', WebsiteStateEnum::LIVE)
            ->when($website, fn ($query) => $query->where('id', $website->id))
            ->with('shop.seoCompetitors')
            ->get();

        $fetched = 0;

        foreach ($websites as $liveWebsite) {
            $domain = StoreSerpResult::normaliseDomain($liveWebsite->domain);

            $fetched += $this->fetchDomain($client, $domain, $liveWebsite, self::OWN_REFERRING_DOMAINS);

            if ($forceBacklinks || $this->areBacklinksDue($liveWebsite)) {
                $this->fetchWebsiteBacklinks($client, $liveWebsite, $domain);
            }

            foreach ($liveWebsite->shop?->seoCompetitors ?? [] as $competitor) {
                $fetched += $this->fetchDomain($client, StoreSerpResult::normaliseDomain($competitor->domain), $liveWebsite, self::COMPETITOR_REFERRING_DOMAINS);
            }
        }

        return $fetched;
    }

    /**
     * @throws DataForSeoException
     */
    private function fetchDomain(DataForSeoClient $client, string $domain, Website $website, int $maxReferringDomains): int
    {
        if ($domain === '' || SeoBacklinkSummary::where('domain', $domain)->whereDate('date', today())->exists()) {
            return 0;
        }

        $target  = ['target' => $domain, 'rank_scale' => 'one_hundred', 'include_subdomains' => true, 'exclude_internal_backlinks' => true];
        $summary = Arr::first($this->call(fn () => $client->live('backlinks/summary/live', $target, $website)) ?? []);

        if (!$summary) {
            return 0;
        }

        $hasPreviousRun = SeoBacklinkSummary::where('domain', $domain)->exists();
        $runStartedAt   = now();
        $isComplete     = $this->storeReferringDomains($client, $domain, $website, $target, $maxReferringDomains);

        $lost = null;

        if ($isComplete) {
            $lost = SeoReferringDomain::where('domain', $domain)
                ->whereNull('lost_at')
                ->where('last_fetched_at', '<', $runStartedAt)
                ->update(['lost_at' => $runStartedAt]);
        }

        SeoBacklinkSummary::updateOrCreate(
            ['domain' => $domain, 'date' => today()],
            [
                'rank'                   => Arr::get($summary, 'rank'),
                'backlinks'              => (int) Arr::get($summary, 'backlinks', 0),
                'referring_domains'      => (int) Arr::get($summary, 'referring_domains', 0),
                'referring_main_domains' => (int) Arr::get($summary, 'referring_main_domains', 0),
                'broken_backlinks'       => (int) Arr::get($summary, 'broken_backlinks', 0),
                'broken_pages'           => (int) Arr::get($summary, 'broken_pages', 0),
                'spam_score'             => Arr::get($summary, 'backlinks_spam_score'),
                'new_referring_domains'  => $hasPreviousRun ? SeoReferringDomain::where('domain', $domain)->where('first_fetched_at', '>=', $runStartedAt)->count() : null,
                'lost_referring_domains' => $hasPreviousRun ? $lost : null,
            ]
        );

        return 1;
    }

    /**
     * Stores the referring domains, strongest first, and says whether the list is complete, which is
     * when a domain missing from it can be called lost.
     *
     * @throws DataForSeoException
     */
    private function storeReferringDomains(DataForSeoClient $client, string $domain, Website $website, array $target, int $maxRows): bool
    {
        $offset = 0;
        $total  = 0;

        do {
            $result = Arr::first($this->call(fn () => $client->live('backlinks/referring_domains/live', [
                ...$target,
                'limit'    => self::ROWS_PER_REQUEST,
                'offset'   => $offset,
                'order_by' => ['rank,desc'],
            ], $website)) ?? []);

            if (!$result) {
                return false;
            }

            $items = Arr::get($result, 'items') ?? [];
            $total = (int) Arr::get($result, 'total_count', 0);
            $rows  = collect($items)->unique(fn (array $item) => Str::lower((string) Arr::get($item, 'domain')))->all();
            $now   = now();

            foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
                SeoReferringDomain::upsert(
                    array_map(fn (array $item) => [
                        'domain'           => $domain,
                        'referring_domain' => Str::lower((string) Arr::get($item, 'domain')),
                        'rank'             => Arr::get($item, 'rank'),
                        'backlinks'        => (int) Arr::get($item, 'backlinks', 0),
                        'is_own_website'   => $this->isOwnWebsite(Arr::get($item, 'domain')),
                        'first_seen'       => $this->time(Arr::get($item, 'first_seen')),
                        'first_fetched_at' => $now,
                        'last_fetched_at'  => $now,
                        'lost_at'          => null,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ], $chunk),
                    ['domain', 'referring_domain'],
                    ['rank', 'backlinks', 'is_own_website', 'first_seen', 'last_fetched_at', 'lost_at', 'updated_at']
                );
            }

            $offset += self::ROWS_PER_REQUEST;
        } while (count($items) === self::ROWS_PER_REQUEST && $offset < $maxRows && $offset < $total);

        return $offset >= $total;
    }

    private function areBacklinksDue(Website $website): bool
    {
        $lastFetchedAt = SeoBacklink::where('website_id', $website->id)->max('fetched_at');

        return !$lastFetchedAt || Carbon::parse($lastFetchedAt)->lt(now()->subDays(self::BACKLINKS_REFRESH_DAYS));
    }

    /**
     * @throws DataForSeoException
     */
    private function fetchWebsiteBacklinks(DataForSeoClient $client, Website $website, string $domain): void
    {
        $lastFetchedAt = SeoBacklink::where('website_id', $website->id)->max('fetched_at');
        $since         = ($lastFetchedAt ? Carbon::parse($lastFetchedAt) : now()->subDays(30))->format('Y-m-d H:i:s P');
        $target        = ['target' => $domain, 'rank_scale' => 'one_hundred', 'include_subdomains' => true, 'exclude_internal_backlinks' => true, 'limit' => self::ROWS_PER_REQUEST];

        $requests = [
            [...$target, 'mode' => 'one_per_domain', 'order_by' => ['domain_from_rank,desc']],
            [...$target, 'mode' => 'as_is', 'filters' => ['is_broken', '=', true], 'order_by' => ['domain_from_rank,desc']],
            [...$target, 'mode' => 'as_is', 'filters' => ['first_seen', '>', $since], 'order_by' => ['first_seen,desc']],
            [...$target, 'mode' => 'as_is', 'backlinks_status_type' => 'lost', 'filters' => ['last_seen', '>', $since], 'order_by' => ['last_seen,desc']],
        ];

        $webpageIdsByPath = $this->webpageIdsByPath($website);

        foreach ($requests as $request) {
            $items = Arr::get(Arr::first($this->call(fn () => $client->live('backlinks/backlinks/live', $request, $website)) ?? []), 'items') ?? [];

            $this->storeBacklinks($website, $items, $webpageIdsByPath);
        }
    }

    private function storeBacklinks(Website $website, array $items, array $webpageIdsByPath): void
    {
        $now = now();

        $rows = collect($items)
            ->filter(fn (array $item) => Arr::get($item, 'url_from') && Arr::get($item, 'url_to'))
            ->map(fn (array $item) => [
                'website_id'         => $website->id,
                'source_url'         => $item['url_from'],
                'source_url_hash'    => md5($item['url_from']),
                'source_domain'      => Str::lower((string) Arr::get($item, 'domain_from')),
                'source_title'       => Str::limit((string) Arr::get($item, 'page_from_title'), 500, '') ?: null,
                'domain_rank'        => Arr::get($item, 'domain_from_rank'),
                'page_rank'          => Arr::get($item, 'rank'),
                'is_own_website'     => $this->isOwnWebsite(Arr::get($item, 'domain_from')),
                'target_url'         => $item['url_to'],
                'target_url_hash'    => md5($item['url_to']),
                'target_path'        => '/'.trim((string) parse_url($item['url_to'], PHP_URL_PATH), '/'),
                'target_webpage_id'  => $this->matchWebpageId($item['url_to'], $webpageIdsByPath),
                'anchor'             => Str::limit((string) Arr::get($item, 'anchor'), 500, '') ?: null,
                'link_type'          => Arr::get($item, 'item_type'),
                'is_dofollow'        => (bool) Arr::get($item, 'dofollow', true),
                'is_broken'          => (bool) Arr::get($item, 'is_broken', false),
                'target_status_code' => Arr::get($item, 'url_to_status_code'),
                'first_seen'         => $this->time(Arr::get($item, 'first_seen')),
                'last_seen'          => $this->time(Arr::get($item, 'last_seen')),
                'lost_at'            => Arr::get($item, 'is_lost') ? $this->time(Arr::get($item, 'last_seen')) : null,
                'fetched_at'         => $now,
                'created_at'         => $now,
                'updated_at'         => $now,
            ])
            ->unique(fn (array $row) => $row['source_url_hash'].$row['target_url_hash'])
            ->values()
            ->all();

        foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
            SeoBacklink::upsert(
                $chunk,
                ['website_id', 'source_url_hash', 'target_url_hash'],
                ['source_domain', 'source_title', 'domain_rank', 'page_rank', 'is_own_website', 'target_path', 'target_webpage_id', 'anchor', 'link_type', 'is_dofollow', 'is_broken', 'target_status_code', 'first_seen', 'last_seen', 'lost_at', 'fetched_at', 'updated_at']
            );
        }
    }

    /**
     * A failure on one domain (a typo in a competitor, a domain DataForSEO refuses) is logged by the
     * client and skipped; only the spent budget stops the whole run.
     *
     * @throws DataForSeoException
     */
    private function call(callable $request): ?array
    {
        try {
            return $request();
        } catch (DataForSeoException $e) {
            if ($e->isBudgetReached()) {
                throw $e;
            }

            return null;
        }
    }

    private function isOwnWebsite(?string $domain): bool
    {
        foreach ($this->ownDomains as $ownDomain) {
            if (StoreSerpResult::isDomain($domain, $ownDomain)) {
                return true;
            }
        }

        return false;
    }

    private function time(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    public function asCommand(Command $command): int
    {
        $website = $command->argument('website') ? Website::where('slug', $command->argument('website'))->firstOrFail() : null;

        try {
            $command->line($this->handle($website, (bool) $command->option('backlinks')).' domains fetched');
        } catch (DataForSeoException $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
