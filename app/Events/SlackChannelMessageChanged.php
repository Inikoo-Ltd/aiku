<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Actions\Chat\Staff\Slack\GetSlackChannelMessages;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackUser;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Arr;

/**
 * A Slack message arrived, changed, was deleted or got a reaction, through the events endpoint only. It goes to the
 * group's Slack channel (grp.slack.{group}), whose authorisation is SlackChannel::visibleTo: a kept public channel is
 * visible to every eligible person of the group, so one subscription serves every channel of the list. A channel that
 * is not kept never broadcasts; a channel visible to some people only (phase 4) needs its own broadcast channel.
 * The raw text a row carries for its author's edit box is never broadcast.
 */
class SlackChannelMessageChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public const int MAX_PAYLOAD_BYTES = 60000;

    public function __construct(public SlackChannel $channel, public SlackMessage $message, public string $kind)
    {
    }

    public function broadcastWhen(): bool
    {
        return SlackChannel::kept()->whereKey($this->channel->id)->exists();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::channelName($this->channel))];
    }

    /**
     * Main workspace channels go on grp.slack.{group}; another workspace's go on its own grp.slack.{group}.team.{team},
     * which only people with an account there may join (INI-595), so their messages never reach the others.
     */
    public static function channelName(SlackChannel $channel): string
    {
        return 'grp.slack.'.$channel->group_id.($channel->slack_team_id ? '.team.'.$channel->slack_team_id : '');
    }

    public function broadcastAs(): string
    {
        return 'slack-message';
    }

    public function broadcastWith(): array
    {
        $ts       = $this->message->ts;
        $threadTs = $this->message->thread_ts;
        $messages = SlackMessage::withTrashed()
            ->where('slack_channel_id', $this->channel->id)
            ->whereIn('ts', array_values(array_unique(array_filter([$ts, $threadTs]))))
            ->with(GetSlackChannelMessages::relations())
            ->get(GetSlackChannelMessages::COLUMNS);
        $rows = collect(GetSlackChannelMessages::make()->rows($this->channel, $messages))
            ->map(fn (array $row) => Arr::except($row, ['text']))
            ->keyBy('ts');

        $payload = [
            'channel_id' => $this->channel->id,
            'kind'       => $this->kind,
            'ts'         => $ts,
            'at'         => microtime(true),
            'message'    => $rows->get($ts),
            'parent'     => $threadTs && $threadTs !== $ts ? $rows->get($threadTs) : null,
            'reactors'   => $this->reactors($messages->firstWhere('ts', $ts)),
        ];

        if (strlen((string) json_encode($payload)) > self::MAX_PAYLOAD_BYTES) {
            return ['message' => null, 'parent' => null, 'reactors' => []] + $payload;
        }

        return $payload;
    }

    /**
     * Who reacted, by aiku user, so each screen marks its own reactions; the row itself is the same for everyone.
     *
     * @return array<string, list<int>>
     */
    private function reactors(?SlackMessage $message): array
    {
        if (!$message || $message->reactions->isEmpty()) {
            return [];
        }
        $users = SlackUser::whereIn('slack_id', $message->reactions->pluck('slack_user_id')->unique()->all())
            ->where('is_bot', false)
            ->whereNotNull('user_id')
            ->pluck('user_id', 'slack_id');

        return $message->reactions
            ->groupBy('name')
            ->map(fn ($group) => $group->map(fn ($reaction) => $users->get($reaction->slack_user_id))->filter()->unique()->values()->all())
            ->filter()
            ->all();
    }
}
