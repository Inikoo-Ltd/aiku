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
 */
class MonitorQueueBacklogs
{
    use AsAction;

    public const int BACKLOG_THRESHOLD = 50000;

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

            $size = $suffix === '' ? (int) $redis->llen($key) : (int) $redis->zcard($key);

            if ($size > 0 && !in_array($queue, $supervised)) {
                $issues[] = "Queue `$queue` has $size jobs in `$key` but NO Horizon supervisor consumes it";
            } elseif ($size > self::BACKLOG_THRESHOLD) {
                $issues[] = "Backlog: `$key` holds $size jobs (threshold ".self::BACKLOG_THRESHOLD.')';
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
