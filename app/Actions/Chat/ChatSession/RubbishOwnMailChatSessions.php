<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Comms\Mailbox\ProcessInboundEmail;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Models\Chat\ChatSession;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Mail from our own shops and staff stopped being imported, but what arrived before it is still
 * sitting in the inbox looking like customers waiting. Put aside rather than deleted, with the
 * reason recorded, so the queue is honest and the same conversations can be found again.
 */
class RubbishOwnMailChatSessions
{
    use AsAction;

    public string $commandSignature = 'chat:rubbish_own_mail {--d|dry-run}';

    public string $commandDescription = 'Mark already imported email conversations from our own shops, staff and staff accounts as rubbish';

    public function handle(bool $dryRun = false, ?Command $command = null): int
    {
        $ours    = ProcessInboundEmail::ourOwnAddresses();
        $marked  = 0;

        ChatSession::where('channel', ChatChannelEnum::EMAIL)
            ->where('is_rubbish', false)
            ->whereIn(ChatSession::raw("lower(metadata->>'email')"), array_keys($ours))
            ->chunkById(200, function ($sessions) use ($ours, $dryRun, $command, &$marked) {
                foreach ($sessions as $session) {
                    $reason = $ours[strtolower((string) data_get($session->metadata, 'email'))] ?? null;

                    if (! $reason) {
                        continue;
                    }

                    $marked++;
                    $command?->line($session->ulid.' '.data_get($session->metadata, 'email').' -> '.$reason->value);

                    if ($dryRun) {
                        continue;
                    }

                    $session->update([
                        'is_rubbish'     => true,
                        'rubbish_at'     => now(),
                        'rubbish_reason' => $reason->value,
                    ]);

                    StoreChatEvent::make()->handle(
                        chatSession: $session,
                        eventType: ChatEventTypeEnum::RUBBISH,
                        actorType: ChatActorTypeEnum::SYSTEM,
                        payload: [
                            'action_type'  => 'rubbish',
                            'reason'       => $reason->value,
                            'reason_label' => $reason->label(),
                            'marked_at'    => now()->toISOString(),
                        ]
                    );
                }
            });

        $command?->info($dryRun ? "Would mark $marked conversations as rubbish" : "Marked $marked conversations as rubbish");

        return $marked;
    }

    public function asCommand(Command $command): int
    {
        $this->handle((bool) $command->option('dry-run'), $command);

        return 0;
    }
}
