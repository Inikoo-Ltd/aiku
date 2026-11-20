<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 13:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreSlackMessage
{
    use AsAction;

    /**
     * A message event of the Slack Events API. Only public channels are kept: private channels and direct messages are never stored.
     * An event that does not say what kind of channel it is from is kept only for a channel already known as public.
     *
     * @param  array<string, mixed>  $event
     */
    public function handle(array $event): ?SlackMessage
    {
        $slackChannelId = Arr::get($event, 'channel');
        $channelType    = Arr::get($event, 'channel_type');
        if (!$slackChannelId || ($channelType !== null && $channelType !== 'channel')) {
            return null;
        }

        $channel = $channelType === 'channel'
            ? SlackChannel::firstOrCreate(['slack_id' => $slackChannelId], ['group_id' => Group::firstOrFail()->id, 'is_member' => true])
            : SlackChannel::where('slack_id', $slackChannelId)->first();
        if (!$channel || !$channel->is_public) {
            return null;
        }

        $message = match (Arr::get($event, 'subtype')) {
            'message_deleted' => $this->delete($channel, (string) Arr::get($event, 'deleted_ts')),
            'message_changed' => $this->store($channel, (array) Arr::get($event, 'message', [])),
            default           => $this->store($channel, $event),
        };

        if ($message && !$message->trashed()) {
            foreach ((array) Arr::get($message->data, 'files', []) as $file) {
                CopySlackMessageFile::dispatch($message, (array) $file);
            }
        }

        return $message;
    }

    /**
     * Stores a message as Slack gives it, in an event or in the channel history; the same message twice is one row.
     *
     * @param  array<string, mixed>  $message
     */
    public function store(SlackChannel $channel, array $message): ?SlackMessage
    {
        $ts = Arr::get($message, 'ts');
        if (!$ts) {
            return null;
        }

        $slackUserId = Arr::get($message, 'user');
        $editedTs    = Arr::get($message, 'edited.ts');

        return tap(SlackMessage::withTrashed()->updateOrCreate(
            ['slack_channel_id' => $channel->id, 'ts' => $ts],
            [
                'thread_ts'     => Arr::get($message, 'thread_ts'),
                'slack_user_id' => $slackUserId,
                'user_id'       => $slackUserId ? (SlackUser::where('slack_id', $slackUserId)->value('user_id') ?? User::where('slack_user_id', $slackUserId)->value('id')) : null,
                'subtype'       => Arr::get($message, 'subtype'),
                'text'          => Arr::get($message, 'text'),
                'sent_at'       => Carbon::createFromTimestamp((float) $ts),
                'edited_at'     => $editedTs ? Carbon::createFromTimestamp((float) $editedTs) : null,
                'data'          => $message,
            ]
        ), fn (SlackMessage $stored) => StoreSlackMessageReaction::make()->fromStoredMessage($stored));
    }

    private function delete(SlackChannel $channel, string $ts): ?SlackMessage
    {
        $message = $channel->messages()->where('ts', $ts)->first();
        $message?->delete();

        return $message;
    }
}
