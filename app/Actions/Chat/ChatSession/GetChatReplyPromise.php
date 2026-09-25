<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 06:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\Reports\IsWithinWorkingHours;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A customer told, while we were closed, that we would answer once we opened: when that opening
 * was, unless an agent has already spoken since. Read from what is already on the session, so a
 * conversation that never made the promise costs nothing to check.
 */
class GetChatReplyPromise
{
    use AsAction;

    public function handle(ChatSession|MetaChatSession $session): ?Carbon
    {
        if (!self::isWaiting($session) || !$session->shop) {
            return null;
        }

        $repliedAt = Carbon::parse(data_get($session->metadata, SendOutOfHoursReply::SENT_KEY));

        return IsWithinWorkingHours::make()->nextOpening($session->shop, $repliedAt)['opens'] ?? null;
    }

    /**
     * Still open and nobody from the team has spoken since the promise, read from fields already
     * on the session. The inbox puts these first; a closed conversation made no promise we owe.
     */
    public static function isWaiting(ChatSession|MetaChatSession $session): bool
    {
        $repliedAt = data_get($session->metadata, SendOutOfHoursReply::SENT_KEY);

        if (!$repliedAt || $session->status === ChatSessionStatusEnum::CLOSED) {
            return false;
        }

        $answeredAt = $session->last_agent_message_at ? Carbon::parse($session->last_agent_message_at) : null;

        return !$answeredAt || $answeredAt->lt(Carbon::parse($repliedAt));
    }

    /**
     * What the inbox shows beside a conversation still waiting on that promise: when it was
     * made, and whether we are already an hour past it.
     *
     * @return array{at: string, overdue: bool}|null
     */
    public static function forList(ChatSession|MetaChatSession $session): ?array
    {
        $promised = self::run($session);

        if (!$promised) {
            return null;
        }

        return [
            'at'      => $promised->toISOString(),
            'overdue' => now()->gt($promised->copy()->addHour()),
        ];
    }
}
