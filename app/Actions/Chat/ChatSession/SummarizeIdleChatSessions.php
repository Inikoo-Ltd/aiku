<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession;

use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class SummarizeIdleChatSessions
{
    use AsAction;

    public string $commandSignature = 'chat:summarise-idle {--d|days=30 : How far back to look} {--i|idle-hours=2 : Quiet for this long} {--u|unclassified : Also those tried before that came back with nothing}';

    public string $commandDescription = 'Summarise and classify conversations that went quiet without being closed, or carried on after their summary';

    /**
     * Closing a conversation summarises it, but most email and a good part of the website
     * conversations are never closed: they just go quiet. This picks those up, and any that
     * carried on after they were summarised.
     */
    public function handle(int $days = 30, int $idleHours = 2, bool $unclassified = false): int
    {
        $dispatched = 0;

        $queries = [
            ChatSession::query()->where('is_rubbish', false),
            MetaChatSession::query(),
        ];

        foreach ($queries as $query) {
            $query
                ->where('is_spam', false)
                ->where('created_at', '>=', now()->subDays($days))
                ->whereNotNull('last_visitor_message_at')
                ->whereRaw('greatest(last_visitor_message_at, last_agent_message_at) < ?', [now()->subHours($idleHours)])
                ->where(fn (Builder $stale) => $stale
                    ->whereNull('summarised_at')
                    ->when($unclassified, fn (Builder $failed) => $failed->orWhereNull('topic'))
                    ->orWhereRaw('summarised_at < greatest(last_visitor_message_at, last_agent_message_at)'))
                ->select('id')
                ->chunkById(200, function ($chatSessions) use (&$dispatched) {
                    foreach ($chatSessions as $chatSession) {
                        SummarizeChatSession::dispatch($chatSession);
                        $dispatched++;
                    }
                });
        }

        return $dispatched;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $dispatched = $this->handle((int) $command->option('days'), (int) $command->option('idle-hours'), (bool) $command->option('unclassified'));
        $command->info("$dispatched conversations queued to be summarised");

        return 0;
    }
}
