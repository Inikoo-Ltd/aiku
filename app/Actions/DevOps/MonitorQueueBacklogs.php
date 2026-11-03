<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Aug 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Guards against the 28 Aug 2026 incident: jobs dispatched to a queue no Horizon
 * supervisor consumes piled up to 6.9M entries / 18GB and took Redis down.
 * Alerts Discord when a queue has no supervisor or its backlog passes the threshold.
 * INI-140 (26 Oct 2026): default was barely consumed for 70 min without crossing the
 * threshold, so the oldest waiting job of the busy queues is checked too.
 * INI-185: pushedAt is when a job was dispatched, so a delayed job's delay is not counted as waiting.
 */
class MonitorQueueBacklogs
{
    use AsAction;

    public const int BACKLOG_THRESHOLD = 50000;

    /**
     * INI-185: queues built for bulk bursts. The nightly time-series run puts ~130k jobs on
     * sales_slave, and an offer launched in every shop queues one job per open basket (~100k)
     * on hydrators-slave-low-priority. Both drain by themselves.
     *
     * @var array<string, int>
     */
    public const array BACKLOG_THRESHOLDS = [
        'sales_slave'                  => 200000,
        'hydrators-slave-low-priority' => 200000,
    ];

    /** @var array<string, int> */
    public const array MAX_WAIT_SECONDS = [
        'default' => 300,
        'urgent'  => 300,
    ];

    public string $commandSignature = 'monitor:queue_backlogs';
    public string $commandDescription = 'Alert Discord on unsupervised queues or queue backlogs over the threshold';

    /**
     * @return array<int, string> list of problems found
     */
    public function handle(?Command $command = null): array
    {
        $redis      = Redis::connection('horizon');
        $supervised = collect(config('horizon.defaults', []))
            ->flatMap(fn (array $supervisor) => (array) ($supervisor['queue'] ?? []))
            ->unique()
            ->all();

        $issues = [];
        foreach ($redis->keys('queues:*') as $fullKey) {
            if (!preg_match('/queues:([^:]+)(:delayed|:reserved)?$/', $fullKey, $matches)) {
                continue;
            }
            $queue  = $matches[1];
            $suffix = $matches[2] ?? '';
            $key    = 'queues:'.$queue.$suffix;

            $size      = $suffix === '' ? (int) $redis->llen($key) : (int) $redis->zcard($key);
            $threshold = self::BACKLOG_THRESHOLDS[$queue] ?? self::BACKLOG_THRESHOLD;

            if ($size > 0 && !in_array($queue, $supervised)) {
                $issues[] = "Queue `$queue` has $size jobs in `$key` but NO Horizon supervisor consumes it";
            } elseif ($size > $threshold) {
                $issues[] = "Backlog: `$key` holds $size jobs (threshold $threshold)";
            }

            if ($suffix === '' && $size > 0 && isset(self::MAX_WAIT_SECONDS[$queue])) {
                $oldestJob = json_decode((string) $redis->lindex($key, 0), true);
                $pushedAt  = (float) ($oldestJob['pushedAt'] ?? 0);
                $waited    = $pushedAt > 0 ? (int) (microtime(true) - $pushedAt) - (int) ($oldestJob['delay'] ?? 0) : 0;
                if ($waited > self::MAX_WAIT_SECONDS[$queue]) {
                    $issues[] = "Stalled: oldest job in `$key` has waited ".intdiv($waited, 60)." min ($size jobs waiting)";
                }
            }
        }

        $issues = array_values(array_unique($issues));

        foreach ($issues as $issue) {
            $command?->error($issue);
        }
        if ($issues) {
            $this->notifyDiscord($issues, $command);
        } else {
            $command?->info('All queues healthy');
        }

        return $issues;
    }

    public function asCommand(Command $command): int
    {
        return $this->handle($command) === [] ? 0 : 1;
    }

    protected function notifyDiscord(array $issues, ?Command $command = null): void
    {
        $webhookUrl = config('services.discord.webhook_url');

        if (!$webhookUrl) {
            $command?->error('Discord webhook URL is not configured. Please set it in config/services.php or .env');

            return;
        }

        $message = "🧨 **Queue Backlog Alert** 🧨   <@&1164019425154969600>\n\n".implode("\n", $issues);

        try {
            Http::post($webhookUrl, [
                'content' => $message,
            ]);
        } catch (\Exception $e) {
            $command?->error('Failed to send Discord notification: '.$e->getMessage());
        }
    }
}
