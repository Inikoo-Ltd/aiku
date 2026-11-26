<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Models\Chat\SlackChannel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;

/**
 * Reads a channel's history and thread replies with the bot token, for public (SyncSlackPublicChannels) and private
 * (SyncSlackPrivateChannels) channels; each says through isStillKept when to stop.
 */
trait WithSlackChannelHistory
{
    private int $added = 0;

    abstract protected function isStillKept(SlackChannel $channel): bool;

    /**
     * The first run walks back from the newest message to the first one and can be stopped and run again; later runs
     * re-read the day before the last run, for messages missed while aiku was not listening.
     */
    protected function syncHistory(PendingRequest $client, SlackChannel $channel): int
    {
        $startedAt = now();
        $window    = $channel->history_synced_at
            ? ['oldest' => (string) $channel->history_synced_at->subDay()->timestamp]
            : ['latest' => $channel->history_backfilled_to];
        $stored = 0;
        $cursor = null;

        do {
            $page = $this->slackCall($client, 'conversations.history', array_filter(['channel' => $channel->slack_id, 'limit' => 200, 'cursor' => $cursor] + $window));
            if (!$this->isStillKept($channel)) {
                return $stored;
            }
            foreach (Arr::get($page, 'messages', []) as $message) {
                $this->added += (int) StoreSlackMessage::make()->store($channel, $message)?->wasRecentlyCreated;
                $stored++;
                if (Arr::get($message, 'reply_count')) {
                    $stored += $this->syncReplies($client, $channel, $message['ts']);
                }
                if (!$channel->history_synced_at) {
                    $channel->update(['history_backfilled_to' => $message['ts']]);
                }
            }
            $cursor = Arr::get($page, 'response_metadata.next_cursor');
        } while ($cursor);

        $channel->update(['history_synced_at' => $startedAt]);

        return $stored;
    }

    protected function syncReplies(PendingRequest $client, SlackChannel $channel, string $threadTs): int
    {
        $stored = 0;
        $cursor = null;

        do {
            $page = $this->slackCall($client, 'conversations.replies', array_filter(['channel' => $channel->slack_id, 'ts' => $threadTs, 'limit' => 200, 'cursor' => $cursor]));
            if (!$this->isStillKept($channel)) {
                return $stored;
            }
            foreach (Arr::get($page, 'messages', []) as $reply) {
                if ($reply['ts'] !== $threadTs) {
                    $this->added += (int) StoreSlackMessage::make()->store($channel, $reply)?->wasRecentlyCreated;
                    $stored++;
                }
            }
            $cursor = Arr::get($page, 'response_metadata.next_cursor');
        } while ($cursor);

        return $stored;
    }
}
