<?php

/*
 * Created: Tue, 27 Oct 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Web\Crawl\AuditWebsite;
use App\Events\BroadcastWebpageLinksChecked;
use App\Models\Web\Webpage;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The broken-link half of "Crawl page": asks every internal page the webpage links to, live, and
 * writes the ones answering with an error (or not at all) into the page check. It runs queued
 * because a page can have a hundred links, and tells the user who asked once it is done.
 * ponytail: stops after MAX_LINKS links; raise it, or check in several jobs, if pages link to more.
 */
class CheckWebpageLinks
{
    use AsAction;

    public const int MAX_LINKS = 300;

    private const int CONCURRENCY = 5;

    public string $jobQueue = 'long-high-priority';

    public int $jobTimeout = 1200;

    public int $jobTries = 1;

    /**
     * @param  array<int, string>  $urls
     *
     * @return array<int, array{url: string, status_code: ?int}>|null null when a newer page check replaced the one this was asked for
     */
    public function handle(Webpage $webpage, string $cacheKey, string $checkedAt, array $urls, ?int $userId = null): ?array
    {
        $brokenLinks = collect($urls)
            ->take(self::MAX_LINKS)
            ->chunk(self::CONCURRENCY)
            ->flatMap(function ($chunk) {
                $responses = Http::pool(fn (Pool $pool) => $chunk->map(
                    fn (string $url) => $pool->as($url)
                        ->withHeaders(['User-Agent' => AuditWebsite::USER_AGENT])
                        ->withOptions(['allow_redirects' => ['max' => 5]])
                        ->when(!app()->isProduction(), fn ($request) => $request->withoutVerifying())
                        ->connectTimeout(10)
                        ->timeout(15)
                        ->get($url)
                )->values()->all());

                return $chunk->map(fn (string $url) => ['url' => $url, 'status_code' => ($responses[$url] ?? null) instanceof Response ? $responses[$url]->status() : null]);
            })
            ->filter(fn (array $link) => $link['status_code'] === null || $link['status_code'] >= 400)
            ->values()
            ->all();

        $check = Cache::get($cacheKey);

        if (($check['checked_at'] ?? null) !== $checkedAt) {
            return null;
        }

        Cache::put($cacheKey, [...$check, 'broken_links' => $brokenLinks, 'links_state' => 'done'], now()->addDays(30));

        if ($userId) {
            BroadcastWebpageLinksChecked::dispatch($userId, $webpage->id, count($brokenLinks));
        }

        return $brokenLinks;
    }
}
