<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 12:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps;

use App\Actions\Helpers\AI\GetOpenRouterBalance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Warns on Discord before OpenRouter runs out, since then every AI feature stops at once.
 */
class MonitorAICredit
{
    use AsAction;

    public string $commandSignature = 'monitor:ai_credit';
    public string $commandDescription = 'Alert Discord when the OpenRouter credit left is low';

    public function handle(?Command $command = null): ?float
    {
        Nightwatch::dontSample();

        $balance = GetOpenRouterBalance::run(fresh: true);

        if ($balance === null) {
            $command?->warn('No OpenRouter balance: key missing or OpenRouter not answering');

            return null;
        }

        if (!$balance['is_low']) {
            Cache::forget($this->throttleKey());
            $command?->info(sprintf('AI credit healthy, $%.2f left', $balance['left']));

            return $balance['left'];
        }

        $issue = sprintf(
            'Only **$%.2f** left to spend on AI (account credit $%.2f%s). When it reaches $0 every AI feature stops: chat summaries, drafts, translations, invoice reading. Top up at https://openrouter.ai/settings/credits',
            $balance['left'],
            $balance['credits_left'],
            $balance['key_limit'] === null ? '' : sprintf(', key limit $%.2f %s with $%.2f left', $balance['key_limit'], $balance['key_limit_reset'] ?? '', $balance['key_limit_remaining'])
        );

        $command?->error($issue);
        $this->alert($issue, $command);

        return $balance['left'];
    }

    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }

    protected function throttleKey(): string
    {
        return 'monitor:ai_credit:alerted';
    }

    protected function alert(string $issue, ?Command $command = null): void
    {
        // ponytail: credit only goes down until someone tops up, so alert once every 6 hours.
        if (!Cache::add($this->throttleKey(), true, now()->addHours(6))) {
            return;
        }

        $webhookUrl = config('services.discord.webhook_url');

        if (!$webhookUrl) {
            $command?->error('Discord webhook URL is not configured');

            return;
        }

        try {
            Http::post($webhookUrl, [
                'content' => "🤖 **AI Credit Low** 🤖   <@&1164019425154969600>\n\n".$issue,
            ]);
        } catch (\Exception $e) {
            $command?->error('Failed to send Discord notification: '.$e->getMessage());
        }
    }
}
