<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A message of a Slack DM or group DM arrived, changed or was deleted (INI-593). Ids only, never words, to each member's own
 * channel (grp.slack-direct.{user}); the screen reads the message again through directVisibleTo.
 */
class SlackDirectMessageChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public SlackChannel $channel, public SlackMessage $message, public string $kind)
    {
    }

    public function broadcastOn(): array
    {
        $userIds = SlackUser::whereIn('slack_id', $this->channel->members()->select('slack_user_id'))->whereNotNull('user_id')->pluck('user_id')->unique();

        return User::whereIn('id', $userIds)->get()
            ->filter(fn (User $user) => SlackChannel::privateReader($user) !== null)
            ->map(fn (User $user) => new PrivateChannel('grp.slack-direct.'.$user->id))
            ->values()->all();
    }

    public function broadcastWhen(): bool
    {
        return SlackChannel::keptDirect()->whereKey($this->channel->id)->exists();
    }

    public function broadcastAs(): string
    {
        return 'slack-message';
    }

    /**
     * @return array{channel_id: int, kind: string, ts: string, user_id: int|null, at: float, message: null, parent: null, reactors: array<string, never>}
     */
    public function broadcastWith(): array
    {
        return ['channel_id' => $this->channel->id, 'kind' => $this->kind, 'ts' => $this->message->ts, 'user_id' => $this->message->user_id, 'at' => microtime(true), 'message' => null, 'parent' => null, 'reactors' => []];
    }

    public static function announce(SlackChannel $channel, SlackMessage $message, string $kind): void
    {
        rescue(fn () => self::dispatch($channel, $message, $kind), report: false);
    }
}
