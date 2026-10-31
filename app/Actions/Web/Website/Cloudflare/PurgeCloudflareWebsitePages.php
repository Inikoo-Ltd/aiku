<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Cloudflare;

use App\Models\Web\Website;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Decorators\JobDecorator;
use RuntimeException;
use Throwable;

/**
 * Clears every Cloudflare edge copy of a website's storefront pages, found by the Cache-Tag that
 * DetectIrisWebsite puts on every storefront response, so assets and images stay cached. Varnish
 * bans burst to thousands a minute while the Free plan allows 5 tag purges a minute per account,
 * so a ban only books one purge at the end of a 10-minute window and every later ban in that
 * window rides on it: an edit reaches anonymous visitors within 10 minutes however long the edge TTL.
 */
class PurgeCloudflareWebsitePages
{
    use AsAction;

    public const int DEBOUNCE_SECONDS = 600;

    public string $jobQueue = 'urgent';

    public array $jobBackoff = [60, 300, 900];

    /**
     * The lock lives exactly as long as the delay, so a ban either finds the booked purge still
     * ahead of it or books the next one; ShouldBeUnique would drop bans landing mid-purge.
     */
    public static function schedule(Website $website): void
    {
        if (!config('iris.cache.varnish') || !$website->cloudflare_zone_id || !$website->cloudflare_token) {
            return;
        }

        if (Cache::add('cloudflare-page-purge:'.$website->id, true, self::DEBOUNCE_SECONDS)) {
            static::dispatch($website)->delay(self::DEBOUNCE_SECONDS);
        }
    }

    public static function scheduleAllWebsites(): void
    {
        Website::where('status', true)->get()->each(self::schedule(...));
    }

    public static function getCacheTag(int $websiteId): string
    {
        return 'aiku-website-'.$websiteId;
    }

    /**
     * @return int|null seconds to wait when Cloudflare rate limits the purge, null once purged
     *
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(Website $website): ?int
    {
        $response = Http::withToken(decrypt($website->cloudflare_token))
            ->acceptJson()
            ->timeout(15)
            ->post("https://api.cloudflare.com/client/v4/zones/$website->cloudflare_zone_id/purge_cache", [
                'tags' => [self::getCacheTag($website->id)],
            ]);

        if ($response->status() === 429) {
            return max(60, (int) $response->header('Retry-After'));
        }

        if (!$response->json('success')) {
            throw new RuntimeException("Cloudflare page purge failed for website $website->id: HTTP {$response->status()} ".json_encode($response->json('errors')));
        }

        return null;
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function asJob(JobDecorator $job, Website $website): void
    {
        if ($retryAfter = $this->handle($website)) {
            $job->release($retryAfter);
        }
    }

    /**
     * Releases on a rate limit count as attempts, so the deadline, not a try count, ends retries.
     */
    public function getJobRetryUntil(): DateTime
    {
        return now()->addHours(6);
    }

    /**
     * The deploy runs this on every web host after a Varnish flush, so the first host takes a short
     * lock and the rest skip. Once Cloudflare answers 429 (bucket of 25, then 5 a minute per account)
     * the remaining sites go to the queue 5 a minute, and nothing here fails the deploy.
     */
    public function purgeAllWebsitesNow(Command $command): void
    {
        if (!config('iris.cache.varnish') || !Cache::add('cloudflare-page-purge:all', true, 120)) {
            $command->line('skipped: Varnish is off here or another host is purging');

            return;
        }

        $websites = Website::where('status', true)->whereNotNull('cloudflare_zone_id')->whereNotNull('cloudflare_token')->get();

        $waitSeconds = null;
        $queued      = 0;
        foreach ($websites as $website) {
            if ($waitSeconds === null) {
                try {
                    $waitSeconds = $this->handle($website);
                } catch (Throwable $exception) {
                    report($exception);
                    static::dispatch($website);
                    $command->line('failed, queued '.$website->slug);

                    continue;
                }

                if ($waitSeconds === null) {
                    $command->line('purged '.$website->slug);

                    continue;
                }
            }

            static::dispatch($website)->delay($waitSeconds + intdiv($queued++, 5) * 60);
            $command->line('rate limited, queued '.$website->slug);
        }
    }

    public function getCommandSignature(): string
    {
        return 'cloudflare:purge-host {website? : website slug; without it every live website is purged now, once per deploy}';
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function asCommand(Command $command): int
    {
        if (!$command->argument('website')) {
            try {
                $this->purgeAllWebsitesNow($command);
            } catch (Throwable $exception) {
                report($exception);
                $command->error('Cloudflare purge skipped: '.$exception->getMessage());
            }

            return 0;
        }

        $website    = Website::where('slug', $command->argument('website'))->firstOrFail();
        $retryAfter = $this->handle($website);

        $command->line($retryAfter ? "rate limited, retry in {$retryAfter}s" : 'purged '.$website->slug);

        return 0;
    }
}
