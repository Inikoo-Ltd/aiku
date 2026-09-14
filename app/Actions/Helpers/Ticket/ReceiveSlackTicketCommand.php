<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

class ReceiveSlackTicketCommand
{
    use AsAction;
    use WithSlack;

    public function handle(Group $group, array $payload): Ticket
    {
        $text     = trim((string) ($payload['text'] ?? ''));
        $reporter = $this->slackUserToAikuUser($payload['user_id'] ?? null);
        [$subject, $description] = array_pad(explode("\n", $text, 2), 2, null);

        return StoreTicket::make()->action($group, [
            'type'          => TicketTypeEnum::HELP->value,
            'kind'          => TicketKindEnum::BUG->value,
            'subject'       => $subject,
            'description'   => trim((string) $description) ?: null,
            'reporter_type' => $reporter ? 'User' : null,
            'reporter_id'   => $reporter?->id,
            'data'          => [
                'slack' => [
                    'user_id'   => $payload['user_id'] ?? null,
                    'user_name' => $payload['user_name'] ?? null,
                    'channel'   => $payload['channel_name'] ?? null,
                ],
            ],
        ]);
    }

    public function asController(Request $request): JsonResponse
    {
        abort_unless($this->slackSignatureIsValid($request), 401);
        if (trim((string) $request->input('text')) === '') {
            ReceiveSlackInteraction::make()->openTicketModal([
                'trigger_id' => $request->input('trigger_id'),
                'user_id'    => $request->input('user_id'),
                'channel_id' => $request->input('channel_id'),
            ]);

            return response()->json([]);
        }

        $ticket = $this->handle(Group::firstOrFail(), $request->all());

        return response()->json([
            'response_type' => 'ephemeral',
            'text'          => $ticket->reference.' raised: '.$ticket->subject."\n".route('grp.tickets.show', $ticket->reference),
        ]);
    }
}
