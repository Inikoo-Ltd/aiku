<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Models\Chat\SlackChannel;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Keeps slack_channels as Slack says it changes. Only channel_created makes a row; a channel that turned private stays marked
 * so nothing more of it is kept on the public path. The aiku bot added to a private channel brings it in through
 * SyncSlackPrivateChannels (INI-431); removed, it gets nothing new.
 */
class UpdateSlackChannelFromEvent
{
    use AsAction;
    use WithSlack;

    public const array EVENTS = [
        'channel_created',
        'channel_rename',
        'channel_archive',
        'channel_unarchive',
        'channel_deleted',
        'channel_left',
        'member_joined_channel',
        'member_left_channel',
    ];

    /**
     * @param  array<string, mixed>  $event
     */
    public function handle(array $event, ?string $botUserId): ?SlackChannel
    {
        $channel   = Arr::get($event, 'channel');
        $slackId   = is_array($channel) ? Arr::get($channel, 'id') : $channel;
        if (!$slackId) {
            return null;
        }
        $isBot = $botUserId && Arr::get($event, 'user') === $botUserId;
        if (Arr::get($event, 'channel_type') === 'G') {
            return match (true) {
                $isBot && Arr::get($event, 'type') === 'member_joined_channel' => tap($this->markPrivate($slackId), fn () => $this->slackClient() && SyncSlackPrivateChannels::dispatch(Group::firstOrFail(), true, $slackId)),
                $isBot && Arr::get($event, 'type') === 'member_left_channel'   => $this->knownChannel($slackId, ['is_public' => false, 'is_member' => false]),
                default                                                         => $this->markPrivate($slackId),
            };
        }

        return match (Arr::get($event, 'type')) {
            'channel_created'                   => $this->publicChannel($slackId, ['name' => Arr::get($channel, 'name')]),
            'channel_rename'                    => $this->knownPublicChannel($slackId, ['name' => Arr::get($channel, 'name')]),
            'channel_archive'                   => $this->knownPublicChannel($slackId, ['is_archived' => true]),
            'channel_unarchive'                 => $this->knownPublicChannel($slackId, ['is_archived' => false]),
            'channel_deleted'                   => $this->knownChannel($slackId, ['deleted_in_slack_at' => now(), 'is_member' => false]),
            'channel_left'                      => $this->knownChannel($slackId, ['is_member' => false]),
            'member_joined_channel'             => $isBot ? $this->knownChannel($slackId, ['is_member' => true]) : null,
            'member_left_channel'               => $isBot ? $this->knownChannel($slackId, ['is_member' => false]) : null,
            default                             => null,
        };
    }

    public function markPrivate(string $slackId): ?SlackChannel
    {
        return $this->knownChannel($slackId, ['is_public' => false]);
    }

    /**
     * Not called: whether the aiku app joins new public channels by itself is still to be decided, and Slack shows a
     * "joined" notice in the channel.
     */
    public function joinNewPublicChannel(SlackChannel $channel): void
    {
        if (!$channel->is_public || $channel->is_archived || $channel->is_member || $channel->deleted_in_slack_at) {
            return;
        }
        $client = $this->slackClient() ?? throw new RuntimeException('Slack bot token is not set');
        $this->slackCall($client, 'conversations.join', ['channel' => $channel->slack_id], 'post');
        $channel->update(['is_member' => true]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function publicChannel(string $slackId, array $changes): SlackChannel
    {
        $channel = SlackChannel::firstOrCreate(['slack_id' => $slackId], ['group_id' => Group::firstOrFail()->id]);
        $channel->update(array_filter($changes, fn ($value) => $value !== null));

        return $channel;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function knownPublicChannel(string $slackId, array $changes): ?SlackChannel
    {
        $channel = SlackChannel::where('slack_id', $slackId)->where('is_public', true)->first();
        $channel?->update(array_filter($changes, fn ($value) => $value !== null));

        return $channel;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function knownChannel(string $slackId, array $changes): ?SlackChannel
    {
        $channel = SlackChannel::where('slack_id', $slackId)->first();
        $channel?->update($changes);

        return $channel;
    }
}
