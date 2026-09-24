<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class ClassifyIdleChatSessionsNoise
{
    use AsAction;

    public string $commandSignature = 'chat:classify-noise {--d|days=7 : How far back to look} {--r|dry-run : Only list what the rules would put aside, ask no model}';

    public string $commandDescription = 'Check strangers\' email and WhatsApp conversations for noise that were missed on arrival';

    /**
     * Conversations are checked as they arrive; this picks up what that missed, and the ones
     * that were already in the queue before there was anything to check them. Website chat is
     * left out: a handful of spam a month there does not pay for reading everybody.
     */
    public function handle(int $days = 7, bool $dryRun = false, ?Command $command = null): int
    {
        $found = 0;

        $queries = [
            ChatSession::query()->where('channel', ChatChannelEnum::EMAIL)->whereNull('web_user_id')->where('is_rubbish', false),
            MetaChatSession::query()->whereNull('customer_id'),
        ];

        foreach ($queries as $query) {
            $query
                ->where('is_spam', false)
                ->whereNull('noise_checked_at')
                ->whereNull('last_agent_message_at')
                ->whereNotNull('last_visitor_message_at')
                ->where('created_at', '>=', now()->subDays($days))
                ->chunkById(200, function ($chatSessions) use (&$found, $dryRun, $command) {
                    foreach ($chatSessions as $chatSession) {
                        if (!$dryRun) {
                            ClassifyChatSessionNoise::dispatch($chatSession);
                            $found++;

                            continue;
                        }

                        $rule = ClassifyChatSessionNoise::make()->verdictByRules($chatSession);
                        if ($rule && $rule['verdict']->isNoise()) {
                            $found++;
                            $who = $chatSession instanceof MetaChatSession ? $chatSession->phone_number : data_get($chatSession->metadata, 'email_from');
                            $command?->line("$chatSession->ulid $who -> {$rule['verdict']->value} ({$rule['note']})");
                        }
                    }
                });
        }

        return $found;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $dryRun = (bool) $command->option('dry-run');
        $found  = $this->handle((int) $command->option('days'), $dryRun, $command);

        $command->info($dryRun ? "$found conversations the rules would put aside" : "$found conversations queued to be checked");

        return 0;
    }
}
