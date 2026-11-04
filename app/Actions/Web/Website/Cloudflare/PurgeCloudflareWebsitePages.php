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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
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
        if (!self::isPurgeable($website)) {
            return;
        }

        self::countBan($website);

        if (Cache::add('cloudflare-page-purge:'.$website->id, true, self::DEBOUNCE_SECONDS)) {
            static::dispatch($website)->delay(self::DEBOUNCE_SECONDS);
        }
    }

    /**
     * For someone breaking a cache by hand: they expect the page fresh at once, not in 10 minutes.
     */
    public static function purgeNow(Website $website): void
    {
        if (self::isPurgeable($website)) {
            self::countBan($website);
            static::dispatch($website, 'manual');
        }
    }

    /**
     * Counts the ban under the chain of aiku classes that led to it (UpdateWebpage>BreakWebpageCache>BanVarnishWebpage),
     * so each purge row says which kinds of change keep wiping the site. A ban from a queued job starts the chain at
     * the job's class. Bans burst to thousands a minute, so they are counted in Redis and stored once per purge.
     */
    protected static function countBan(Website $website): void
    {
        $chain = collect(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 60))
            ->map(fn (array $frame) => isset($frame['object']) ? get_class($frame['object']) : null)
            ->filter(fn (?string $class) => $class && str_starts_with($class, 'App\\Actions\\') && $class !== self::class)
            ->map(fn (string $class) => class_basename($class))
            ->unique()
            ->reverse()
            ->implode('>');

        try {
            Redis::pipeline(function ($pipe) use ($website, $chain) {
                $pipe->hincrby(self::banSourcesKey($website->id), $chain ?: 'unknown', 1);
                $pipe->expire(self::banSourcesKey($website->id), 86400);
            });
        } catch (Throwable) {
            return;
        }
    }

    protected static function banSourcesKey(int $websiteId): string
    {
        return 'cloudflare-page-purge-ban-sources:'.$websiteId;
    }

    protected function recordPurge(Website $website, string $trigger): void
    {
        try {
            [$banSources] = Redis::transaction(function ($redis) use ($website) {
                $redis->hgetall(self::banSourcesKey($website->id));
                $redis->del(self::banSourcesKey($website->id));
            });
            $banSources = array_map('intval', $banSources ?: []);
            arsort($banSources);

            DB::table('website_edge_purges')->insert([
                'website_id'  => $website->id,
                'trigger'     => $trigger,
                'bans'        => array_sum($banSources),
                'ban_sources' => json_encode($banSources ?: (object) []),
                'created_at'  => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected static function isPurgeable(Website $website): bool
    {
        return config('iris.cache.varnish') && $website->cloudflare_zone_id && $website->cloudflare_token;
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
    public function handle(Website $website, string $trigger = 'scheduled'): ?int
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

        $this->recordPurge($website, $trigger);

        return null;
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function asJob(JobDecorator $job, Website $website, string $trigger = 'scheduled'): void
    {
        if ($retryAfter = $this->handle($website, $trigger)) {
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
    public function purgeAllWebsitesNow(?Command $command = null): void
    {
        if (!config('iris.cache.varnish') || !Cache::add('cloudflare-page-purge:all', true, 120)) {
            $command?->line('skipped: Varnish is off here or another host is purging');

            return;
        }

        $websites = Website::where('status', true)->whereNotNull('cloudflare_zone_id')->whereNotNull('cloudflare_token')->get();

        $waitSeconds = null;
        $queued      = 0;
        foreach ($websites as $website) {
            if ($waitSeconds === null) {
                try {
                    $waitSeconds = $this->handle($website, 'deploy');
                } catch (Throwable $exception) {
                    report($exception);
                    static::dispatch($website, 'deploy');
                    $command?->line('failed, queued '.$website->slug);

                    continue;
                }

                if ($waitSeconds === null) {
                    $command?->line('purged '.$website->slug);

                    continue;
                }
            }

            static::dispatch($website, 'deploy')->delay($waitSeconds + intdiv($queued++, 5) * 60);
            $command?->line('rate limited, queued '.$website->slug);
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
        $retryAfter = $this->handle($website, 'manual');

        $command->line($retryAfter ? "rate limited, retry in {$retryAfter}s" : 'purged '.$website->slug);

        return 0;
    }
}
