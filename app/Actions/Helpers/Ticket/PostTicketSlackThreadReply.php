<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Models\Helpers\Ticket;
use Lorisleiva\Actions\Concerns\AsAction;

class PostTicketSlackThreadReply
{
    use AsAction;
    use WithSlack;

    public function handle(Ticket $ticket, string $text): void
    {
        $channel  = data_get($ticket->data, 'slack.channel_id') ?? data_get($ticket->data, 'slack_alert.channel');
        $threadTs = data_get($ticket->data, 'slack.ts') ?? data_get($ticket->data, 'slack_alert.ts');
        if (!$channel || !$threadTs || !$client = $this->slackClient()) {
            return;
        }

        $client->post('chat.postMessage', [
            'channel'   => $channel,
            'thread_ts' => $threadTs,
            'text'      => $text,
        ]);
    }
}
