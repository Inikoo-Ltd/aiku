<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps;

use App\Models\CRM\RetinaApiRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A customer integration stuck in a retry loop can write far more request logs in an hour
 * than the whole platform does in a week. Alerts Discord so the client can be told before
 * the nightly prune has to clean it up.
 */
class MonitorRetinaApiInflow
{
    use AsAction;

    public const int CUSTOMER_HOURLY_THRESHOLD = 1000;

    public string $commandSignature = 'monitor:retina_api_inflow';
    public string $commandDescription = 'Alert Discord when a customer floods the API in the last hour';

    /**
     * @return array<int, string> list of problems found
     */
    public function handle(?Command $command = null): array
    {
        $floods = RetinaApiRequest::query()
            ->selectRaw('customer_id, count(*) as calls, count(*) filter (where status >= 400) as failures')
            ->where('created_at', '>', now()->subHour())
            ->groupBy('customer_id')
            ->havingRaw('count(*) > ?', [self::CUSTOMER_HOURLY_THRESHOLD])
            ->orderByRaw('count(*) desc')
            ->get();

        $issues = [];
        foreach ($floods as $flood) {
            $issues[] = "Customer `$flood->customer_id` made $flood->calls API calls in the last hour ($flood->failures failed, threshold ".self::CUSTOMER_HOURLY_THRESHOLD.')';
        }

        foreach ($issues as $issue) {
            $command?->error($issue);
        }
        if ($issues) {
            $this->notifyDiscord($issues, $command);
        } else {
            $command?->info('API inflow normal');
        }

        return $issues;
    }

    public function asCommand(Command $command): int
    {
        return $this->handle($command) === [] ? 0 : 1;
    }

    /**
     * @param  array<int, string>  $issues
     */
    protected function notifyDiscord(array $issues, ?Command $command = null): void
    {
        $webhookUrl = config('services.discord.webhook_url');

        if (!$webhookUrl) {
            $command?->error('Discord webhook URL is not configured. Please set it in config/services.php or .env');

            return;
        }

        $message = "🌊 **API Inflow Alert** 🌊\n\n".implode("\n", $issues);

        try {
            Http::post($webhookUrl, [
                'content' => $message,
            ]);
        } catch (\Exception $e) {
            $command?->error('Failed to send Discord notification: '.$e->getMessage());
        }
    }
}
