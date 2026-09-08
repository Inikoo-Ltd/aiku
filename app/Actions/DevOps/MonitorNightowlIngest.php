<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Sep 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Guards against the 2 Sep 2026 outage: the agent kept running, listening and
 * reporting healthy while writing telemetry into a deleted inode, so nothing
 * reached Postgres for five days and no liveness check noticed. The only
 * trustworthy signal is whether rows are still arriving, asked from outside
 * the agent.
 */
class MonitorNightowlIngest
{
    use AsAction;

    public const int STALE_AFTER_MINUTES = 30;

    public string $commandSignature = 'monitor:nightowl_ingest';
    public string $commandDescription = 'Alert Discord when NightOwl telemetry stops arriving';

    public function handle(?Command $command = null): ?int
    {
        Nightwatch::dontSample();

        try {
            $newestRow = DB::connection('nightowl')
                ->table('nightowl_requests_v2')
                ->max('created_at');
        } catch (\Exception $e) {
            $this->alert('NightOwl database is unreachable: '.$e->getMessage(), $command);

            return null;
        }

        if ($newestRow === null) {
            $this->alert('NightOwl has no telemetry at all in `nightowl_requests_v2`.', $command);

            return null;
        }

        $staleMinutes = (int) now()->diffInMinutes($newestRow, true);

        if ($staleMinutes > self::STALE_AFTER_MINUTES) {
            $this->alert(
                "NightOwl has ingested nothing for $staleMinutes minutes (newest row $newestRow). ".
                'The agent may be alive but writing to a deleted buffer — check '.
                '`sudo ls -l /proc/$(pgrep -f nightowl:agent)/fd | grep deleted` on boro and helio.',
                $command
            );

            return $staleMinutes;
        }

        Cache::forget($this->throttleKey());
        $command?->info("NightOwl ingest healthy, newest row $staleMinutes minutes old");

        return $staleMinutes;
    }

    public function asCommand(Command $command): int
    {
        $staleMinutes = $this->handle($command);

        return $staleMinutes === null || $staleMinutes > self::STALE_AFTER_MINUTES ? 1 : 0;
    }

    protected function throttleKey(): string
    {
        return 'monitor:nightowl_ingest:alerted';
    }

    protected function alert(string $issue, ?Command $command = null): void
    {
        $command?->error($issue);

        // ponytail: a stalled agent stays stalled, so alert once every 6 hours
        // rather than on every run until someone restarts it.
        if (!Cache::add($this->throttleKey(), true, now()->addHours(6))) {
            return;
        }

        $webhookUrl = config('services.discord.webhook_url');

        if (!$webhookUrl) {
            $command?->error('Discord webhook URL is not configured. Please set it in config/services.php or .env');

            return;
        }

        try {
            Http::post($webhookUrl, [
                'content' => "🦉 **NightOwl Ingest Alert** 🦉   <@&1164019425154969600>\n\n".$issue,
            ]);
        } catch (\Exception $e) {
            $command?->error('Failed to send Discord notification: '.$e->getMessage());
        }
    }
}
