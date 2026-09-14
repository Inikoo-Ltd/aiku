<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 00:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Helpers\Ticket;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One message per ticket in the tickets channel, edited in place so it always shows the current status and assignee.
 */
class SyncTicketSlackAlert
{
    use AsAction;
    use WithSlack;

    public function handle(Ticket $ticket): void
    {
        $channel = config('services.slack.notifications.tickets_channel');
        if (!$channel || !$client = $this->slackClient()) {
            return;
        }

        $ticket->load(['assignee', 'reporter']);
        $alert = data_get($ticket->data, 'slack_alert');
        $payload = [
            'channel' => $alert['channel'] ?? $channel,
            'text'    => $ticket->reference.' '.$ticket->subject,
            'blocks'  => [
                ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => $ticket->reference.' · '.Str::limit($ticket->subject, 120)]],
                ['type' => 'section', 'fields' => [
                    ['type' => 'mrkdwn', 'text' => '*Status:* '.TicketStatusEnum::labels()[$ticket->status->value]],
                    ['type' => 'mrkdwn', 'text' => '*Assigned to:* '.($ticket->assignee?->username ?? '—')],
                    ['type' => 'mrkdwn', 'text' => '*Reported by:* '.($ticket->reporter?->username ?? $ticket->reporter?->contact_name ?? '—')],
                ]],
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '<'.route('grp.tickets.show', $ticket->reference).'|Show details>']],
            ],
        ];

        if ($alert) {
            $client->post('chat.update', $payload + ['ts' => $alert['ts']]);

            return;
        }

        $response = $client->post('chat.postMessage', $payload);
        if ($response->json('ok')) {
            $ticket->update(['data' => array_merge($ticket->data ?? [], ['slack_alert' => ['channel' => $response->json('channel'), 'ts' => $response->json('ts')]])]);
        }
    }
}
