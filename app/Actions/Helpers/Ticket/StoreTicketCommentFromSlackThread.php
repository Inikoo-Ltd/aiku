<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketComment;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreTicketCommentFromSlackThread
{
    use AsAction;
    use WithSlack;

    /**
     * @param  array{channel?: string|null, thread_ts?: string|null, ts?: string|null, user?: string|null, text?: string|null, bot_id?: string|null, subtype?: string|null}  $event
     */
    public function handle(array $event): ?TicketComment
    {
        $text = trim((string) Arr::get($event, 'text'));
        if (Arr::get($event, 'bot_id') || Arr::get($event, 'subtype') || $text === '' || !Arr::get($event, 'thread_ts') || Arr::get($event, 'thread_ts') === Arr::get($event, 'ts')) {
            return null;
        }

        $ticket = Ticket::where(
            fn ($query) => $query
            ->where(fn ($origin) => $origin->where('data->slack->ts', Arr::get($event, 'thread_ts'))->where('data->slack->channel_id', Arr::get($event, 'channel')))
            ->orWhere(fn ($alert) => $alert->where('data->slack_alert->ts', Arr::get($event, 'thread_ts'))->where('data->slack_alert->channel', Arr::get($event, 'channel')))
        )->first();
        $author = $ticket ? $this->slackUserToAikuUser(Arr::get($event, 'user')) : null;
        if (!$ticket || !$author) {
            return null;
        }

        return StoreTicketComment::make()->action($ticket, $author, ['body' => $text], mirrorToSlack: false);
    }
}
