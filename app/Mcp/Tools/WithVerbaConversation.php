<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;

/**
 * Verba through MCP (INI-596): a Verba conversation is reached only by its own participants, never by role (task
 * supervisors and departments included), and a Slack channel only through SlackChannel::readableBy. Anything else,
 * existing or not, gets the same answer, so a private name never leaks.
 */
trait WithVerbaConversation
{
    public const string NOT_FOUND = 'No Verba conversation or V·Desk with that id that this user can read. Use verba-tool without a conversation to list them.';

    protected function verbaConversation(User $user, string $id): StaffConversation|SlackChannel|null
    {
        if (preg_match('/^slack-(\d{1,18})$/', $id, $match)) {
            return SlackChannel::readableBy($user)->whereKey((int) $match[1])->first();
        }

        if (!preg_match('/^[0-9A-Za-z]{26}$/', $id)) {
            return null;
        }

        $conversation = StaffConversation::whereIn('ulid', array_unique([$id, strtoupper($id), strtolower($id)]))->where('group_id', $user->group_id)->first();

        return $conversation?->hasParticipant($user) ? $conversation : null;
    }

    protected static function verbaId(StaffConversation|SlackChannel $conversation): string
    {
        return $conversation instanceof SlackChannel ? 'slack-'.$conversation->id : $conversation->ulid;
    }
}
