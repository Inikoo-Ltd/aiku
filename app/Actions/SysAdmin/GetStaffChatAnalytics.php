<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin;

use App\Models\Chat\StaffConversation;
use App\Models\Chat\StaffMessage;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetStaffChatAnalytics
{
    use AsObject;

    public function handle(Group $group, int $days = 30): array
    {
        $since = now()->subDays($days);

        $base = StaffMessage::join('staff_conversations', 'staff_conversations.id', '=', 'staff_messages.staff_conversation_id')
            ->where('staff_conversations.group_id', $group->id)
            ->where('staff_messages.created_at', '>=', $since);

        $totals = (clone $base)
            ->selectRaw('
                count(*) as messages,
                count(distinct staff_messages.user_id) as users,
                count(distinct staff_messages.staff_conversation_id) as conversations,
                count(*) filter (where staff_messages.media_id is not null) as media_messages,
                count(*) filter (where staff_conversations.context_type is not null) as context_messages,
                count(*) filter (where staff_messages.parent_id is not null) as replies
            ')
            ->first();

        $translated = (clone $base)
            ->join('staff_message_translations', 'staff_message_translations.staff_message_id', '=', 'staff_messages.id')
            ->count();

        $reactions = (clone $base)
            ->join('staff_message_reactions', 'staff_message_reactions.staff_message_id', '=', 'staff_messages.id')
            ->count();

        $daily = (clone $base)
            ->selectRaw("
                to_char(staff_messages.created_at::date, 'YYYY-MM-DD') as date,
                count(*) filter (where staff_conversations.context_type is null) as dm_messages,
                count(*) filter (where staff_conversations.context_type is not null) as context_messages,
                count(distinct staff_messages.user_id) as users
            ")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $topUsers = (clone $base)
            ->join('users', 'users.id', '=', 'staff_messages.user_id')
            ->selectRaw('users.username, count(*) as messages, count(distinct staff_messages.staff_conversation_id) as conversations, max(staff_messages.created_at) as last_message_at')
            ->groupBy('users.username')
            ->orderByDesc('messages')
            ->limit(10)
            ->get();

        $topPairs = (clone $base)
            ->selectRaw('staff_messages.staff_conversation_id, count(*) as messages, max(staff_messages.created_at) as last_message_at')
            ->whereNull('staff_conversations.context_type')
            ->where('staff_conversations.type', 'dm')
            ->groupBy('staff_messages.staff_conversation_id')
            ->orderByDesc('messages')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'conversation_id' => $row->staff_conversation_id,
                'members'         => $this->participantNames($row->staff_conversation_id),
                'messages'        => (int) $row->messages,
                'last_message_at' => $row->last_message_at,
            ]);

        $byContext = StaffConversation::where('group_id', $group->id)
            ->whereNotNull('context_type')
            ->selectRaw("regexp_replace(context_type, '^.*\\\\', '') as context, count(*) as conversations")
            ->groupBy('context_type')
            ->orderByDesc('conversations')
            ->get();

        $hourly = (clone $base)
            ->selectRaw('extract(hour from staff_messages.created_at)::int as hour, count(*) as messages')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('messages', 'hour');

        $unreadConversations = DB::table('staff_conversation_participants')
            ->join('staff_conversations', 'staff_conversations.id', '=', 'staff_conversation_participants.staff_conversation_id')
            ->where('staff_conversations.group_id', $group->id)
            ->whereNotNull('staff_conversations.last_message_at')
            ->where(function ($query) {
                $query->whereNull('staff_conversation_participants.last_read_at')
                    ->orWhereColumn('staff_conversation_participants.last_read_at', '<', 'staff_conversations.last_message_at');
            })
            ->count();

        return [
            'days'                 => $days,
            'messages'             => (int) $totals->messages,
            'users'                => (int) $totals->users,
            'conversations'        => (int) $totals->conversations,
            'media_messages'       => (int) $totals->media_messages,
            'context_messages'     => (int) $totals->context_messages,
            'replies'              => (int) $totals->replies,
            'translated'           => $translated,
            'reactions'            => $reactions,
            'unread_conversations' => $unreadConversations,
            'daily'                => $daily,
            'hourly'               => array_map(fn ($hour) => (int) ($hourly[$hour] ?? 0), range(0, 23)),
            'top_users'            => $topUsers,
            'top_pairs'            => $topPairs,
            'by_context'           => $byContext,
        ];
    }

    private function participantNames(int $conversationId): string
    {
        return DB::table('staff_conversation_participants')
            ->join('users', 'users.id', '=', 'staff_conversation_participants.user_id')
            ->where('staff_conversation_id', $conversationId)
            ->orderBy('users.username')
            ->pluck('users.username')
            ->implode(' ↔ ');
    }
}
