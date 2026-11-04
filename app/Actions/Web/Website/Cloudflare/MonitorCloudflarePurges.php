<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 19:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Cloudflare;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Warns on Discord when Cloudflare keeps refusing edge purges (Free plan: 5 a minute per account),
 * so storefront edits stop showing to logged-out visitors on time.
 */
class MonitorCloudflarePurges
{
    use AsAction;

    public const int MAX_REFUSALS_PER_HOUR = 20;

    public const int MAX_WAITING_MINUTES = 30;

    public string $commandSignature = 'monitor:cloudflare_purges';

    public string $commandDescription = 'Alert Discord when Cloudflare rate limits storefront edge purges';

    /**
     * @return array{refusals: int, waiting: Collection<int, object>, ban_sources: Collection<int, object>}
     */
    public function handle(?Command $command = null): array
    {
        Nightwatch::dontSample();

        $refusals = DB::table('website_edge_purges')
            ->where('trigger', PurgeCloudflareWebsitePages::RATE_LIMITED)
            ->where('created_at', '>', now()->subHour())
            ->count();

        $waiting = DB::table('website_edge_purges as refused')
            ->join('websites', 'websites.id', '=', 'refused.website_id')
            ->where('refused.trigger', PurgeCloudflareWebsitePages::RATE_LIMITED)
            ->where('refused.created_at', '>', now()->subHours(6))
            ->where('websites.status', true)
            ->whereNotNull('websites.cloudflare_zone_id')
            ->whereNotNull('websites.cloudflare_token')
            ->whereNotExists(fn ($query) => $query->from('website_edge_purges as purged')
                ->whereColumn('purged.website_id', 'refused.website_id')
                ->where('purged.trigger', '!=', PurgeCloudflareWebsitePages::RATE_LIMITED)
                ->whereColumn('purged.created_at', '>', 'refused.created_at'))
            ->groupBy('websites.id', 'websites.domain')
            ->havingRaw('min(refused.created_at) < ?', [now()->subMinutes(self::MAX_WAITING_MINUTES)])
            ->selectRaw('websites.domain, min(refused.created_at) as waiting_since')
            ->get();

        $banSources = DB::table('website_edge_purges')
            ->crossJoin(DB::raw('jsonb_each_text(ban_sources) as source'))
            ->where('created_at', '>', now()->subHour())
            ->groupBy('source.key')
            ->selectRaw('source.key as source, sum(source.value::int) as bans')
            ->orderByDesc('bans')
            ->limit(5)
            ->get();

        $command?->info("Cloudflare purge refusals in the last hour: $refusals, websites waiting over ".self::MAX_WAITING_MINUTES.' min: '.$waiting->count());

        if ($refusals > self::MAX_REFUSALS_PER_HOUR || $waiting->isNotEmpty()) {
            $this->alert($refusals, $waiting, $banSources, $command);
        }

        return ['refusals' => $refusals, 'waiting' => $waiting, 'ban_sources' => $banSources];
    }

    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }

    /**
     * @param Collection<int, object> $waiting
     * @param Collection<int, object> $banSources
     */
    protected function alert(int $refusals, Collection $waiting, Collection $banSources, ?Command $command): void
    {
        $webhookUrl = config('services.discord.webhook_url');
        if (!$webhookUrl) {
            $command?->error('Discord webhook URL is not configured');

            return;
        }

        $lines = ["Cloudflare refused **$refusals** storefront edge purges in the last hour (Free plan: 5 a minute per account)."];

        if ($waiting->isNotEmpty()) {
            $lines[] = 'Logged-out visitors still see old pages on: '.$waiting->map(fn (object $row) => "$row->domain (since ".substr($row->waiting_since, 11, 5).' UTC)')->implode(', ');
        }

        if ($banSources->isNotEmpty()) {
            $lines[] = "Changes asking for purges this hour:\n".$banSources->map(fn (object $row) => "- $row->source: $row->bans")->implode("\n");
        }

        try {
            Http::post($webhookUrl, [
                'content' => "☁️ **Cloudflare purges rate limited** ☁️   <@&1164019425154969600>\n\n".implode("\n\n", $lines),
            ]);
        } catch (Throwable $e) {
            $command?->error('Failed to send Discord notification: '.$e->getMessage());
        }
    }
}
