<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackChannelMember;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * member_joined_channel and member_left_channel for anyone, the aiku bot included, on a kept public channel or a private
 * channel the bot brought in (INI-431, whose readers are these members);
 * an unknown channel is never created here.
 */
class UpdateSlackChannelMember
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $event
     */
    public function handle(array $event): ?SlackChannelMember
    {
        $slackUserId = Arr::get($event, 'user');
        $slackId     = Arr::get($event, 'channel');
        if (!is_string($slackUserId) || !is_string($slackId)) {
            return null;
        }
        $channel = Arr::get($event, 'channel_type') === 'G'
            ? SlackChannel::keptPrivate()->where('slack_id', $slackId)->first()
            : SlackChannel::kept()->where('slack_id', $slackId)->first();
        if (!$channel) {
            return null;
        }

        if (Arr::get($event, 'type') === 'member_left_channel') {
            $channel->members()->where('slack_user_id', $slackUserId)->delete();

            return null;
        }

        return $channel->members()->firstOrCreate(['slack_user_id' => $slackUserId], ['joined_at' => now()]);
    }
}
