<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\PhoneCall;

use App\Enums\CRM\Livechat\ChatPhoneCallStatusEnum;
use App\Models\Chat\ChatPhoneCall;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class AutoCloseStaleChatPhoneCalls
{
    use AsAction;
    use WithChatPhoneCall;

    public string $commandSignature = 'chat:close-stale-phone-calls';

    public string $commandDescription = 'Close phone calls left running past the maximum length';

    /**
     * An agent on a call is handed no new conversations, so a call nobody ended would quietly
     * take that person out of the rota overnight. Past the limit it is closed and marked as
     * closed automatically, which is what the index shows instead of a time nobody spent.
     */
    public function handle(): int
    {
        $cutOff = now()->subMinutes((int) config('chat.phone_call.max_minutes'));

        $calls = ChatPhoneCall::inProgress()
            ->where('started_at', '<=', $cutOff)
            ->get();

        foreach ($calls as $call) {
            $this->closeCall($call, ChatPhoneCallStatusEnum::AUTO_CLOSED);
        }

        return $calls->count();
    }

    public function asCommand(Command $command): int
    {
        $command->info(sprintf('%d phone call(s) closed automatically', $this->handle()));

        return 0;
    }
}
