<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackFile;
use App\Models\Chat\SlackMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Marks a kept message of a public channel as pinned or not; Slack is never asked for the message, and a pin of something
 * aiku does not hold is skipped. A pinned file counts as a pin of the message it was first shared in.
 */
class StoreSlackPin
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $item  one item of pins.list, or the item of a pin_added / pin_removed event
     */
    public function handle(SlackChannel $channel, array $item, bool $pinned, ?string $pinnedBy = null): ?SlackMessage
    {
        if (!$channel->is_public || $channel->is_ignored || $channel->deleted_in_slack_at) {
            return null;
        }

        $message = match (Arr::get($item, 'type')) {
            'message' => $channel->messages()->where('ts', (string) Arr::get($item, 'message.ts'))->first(),
            'file'    => $channel->messages()->whereKey(SlackFile::where('slack_id', (string) Arr::get($item, 'file.id'))->value('slack_message_id'))->first(),
            default   => null,
        };
        if (!$message) {
            return null;
        }

        $created = Arr::get($item, 'created');
        $message->update($pinned
            ? ['pinned_at' => $created ? Carbon::createFromTimestamp((int) $created) : now(), 'pinned_by_slack_user_id' => Arr::get($item, 'created_by', $pinnedBy)]
            : ['pinned_at' => null, 'pinned_by_slack_user_id' => null]);

        return $message;
    }

    /**
     * A pin_added or pin_removed event of the Slack Events API.
     *
     * @param  array<string, mixed>  $event
     */
    public function fromEvent(array $event): ?SlackMessage
    {
        $channel = SlackChannel::where('slack_id', (string) Arr::get($event, 'channel_id'))->first();

        return $channel ? $this->handle($channel, (array) Arr::get($event, 'item', []), Arr::get($event, 'type') === 'pin_added', Arr::get($event, 'user')) : null;
    }
}
