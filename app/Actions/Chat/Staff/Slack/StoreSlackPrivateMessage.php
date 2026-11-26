<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The private path (INI-431): a message event of a Slack private channel the aiku bot was added to. StoreSlackMessage::handle
 * keeps refusing every group, im and mpim event, so the public path is never a way in. DMs and group DMs never come here.
 * Nothing leaves the row: no broadcast on the group's Slack channel, no file copy (served to members only from INI-441),
 * no mention notifications and no Home stories, until the members-only paths exist.
 */
class StoreSlackPrivateMessage
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $event
     */
    public function handle(array $event): ?SlackMessage
    {
        if (Arr::get($event, 'channel_type') !== 'group' || !is_string($slackChannelId = Arr::get($event, 'channel'))) {
            return null;
        }
        $channel = SlackChannel::keptPrivate()->where('is_member', true)->where('slack_id', $slackChannelId)->first();
        if (!$channel) {
            return null;
        }

        return match (Arr::get($event, 'subtype')) {
            'message_deleted' => tap($channel->messages()->where('ts', (string) Arr::get($event, 'deleted_ts'))->first(), fn (?SlackMessage $message) => $message?->delete()),
            'message_changed' => StoreSlackMessage::make()->store($channel, (array) Arr::get($event, 'message', [])),
            default           => StoreSlackMessage::make()->store($channel, $event),
        };
    }
}
