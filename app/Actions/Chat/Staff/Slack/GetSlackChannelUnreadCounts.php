<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Unread messages per Slack channel for one person, in one query over the (slack_channel_id, ts) index. Counted like
 * the main stream: thread replies stay out unless also sent to the channel, and so do system lines, deleted messages
 * and the person's own messages. Each channel stops counting at CAP, as the list shows 99+.
 */
class GetSlackChannelUnreadCounts
{
    use AsObject;

    public const int CAP = 100;

    /**
     * $slackChannelIds must come from SlackChannel::visibleTo: a read position outlives a channel turned private.
     *
     * @param  list<int>  $slackChannelIds
     * @return array<int, array{count: int, last_read_ts: string}>
     */
    public function handle(User $user, array $slackChannelIds): array
    {
        if ($slackChannelIds === []) {
            return [];
        }

        $mySlackIds   = SlackUser::where('user_id', $user->id)->pluck('slack_id')->all();
        $notMine      = $mySlackIds === [] ? '' : 'AND (m.slack_user_id IS NULL OR m.slack_user_id NOT IN ('.implode(',', array_fill(0, count($mySlackIds), '?')).'))';
        $system       = implode(',', array_fill(0, count(GetSlackChannelMessages::SYSTEM_SUBTYPES), '?'));
        $placeholders = implode(',', array_fill(0, count($slackChannelIds), '?'));

        $rows = DB::select(
            "SELECT r.slack_channel_id, r.last_read_ts, unread.count FROM slack_channel_reads r
            CROSS JOIN LATERAL (
                SELECT count(*) AS count FROM (
                    SELECT 1 FROM slack_messages m
                    WHERE m.slack_channel_id = r.slack_channel_id AND m.ts > r.last_read_ts AND m.deleted_at IS NULL
                    AND (m.thread_ts IS NULL OR m.thread_ts = m.ts OR m.subtype = 'thread_broadcast')
                    AND (m.subtype IS NULL OR m.subtype NOT IN ($system))
                    AND m.user_id IS DISTINCT FROM ?
                    $notMine
                    LIMIT ".self::CAP."
                ) capped
            ) unread
            WHERE r.user_id = ? AND r.slack_channel_id IN ($placeholders)",
            [...GetSlackChannelMessages::SYSTEM_SUBTYPES, $user->id, ...$mySlackIds, $user->id, ...$slackChannelIds]
        );

        return collect($rows)->mapWithKeys(fn ($row) => [(int) $row->slack_channel_id => ['count' => (int) $row->count, 'last_read_ts' => $row->last_read_ts]])->all();
    }
}
