<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatSessionClosedByTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Chat\ChatSession;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class CloseEmptyChatSessions
{
    use AsAction;

    public string $commandSignature = 'chat:close-empty {--H|hours=24 : Untouched for this long}';

    public string $commandDescription = 'Close website chat sessions nobody ever wrote in';

    /**
     * Opening the chat widget starts a session, and most visitors close it without typing,
     * leaving it waiting forever. Nothing was said, so it is closed without a system message,
     * a summary or anything the visitor would see; the widget starts afresh if they come back.
     */
    public function handle(int $hours = 24): int
    {
        return ChatSession::query()
            ->where('status', ChatSessionStatusEnum::WAITING->value)
            ->where('created_at', '<', now()->subHours($hours))
            ->whereDoesntHave('messages', fn ($messages) => $messages->withTrashed())
            ->whereDoesntHave('assignments')
            ->update([
                'status'    => ChatSessionStatusEnum::CLOSED->value,
                'closed_by' => ChatSessionClosedByTypeEnum::SYSTEM->value,
                'closed_at' => now(),
            ]);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $command->info(sprintf('%d empty chat sessions closed', $this->handle((int) $command->option('hours'))));

        return 0;
    }
}
