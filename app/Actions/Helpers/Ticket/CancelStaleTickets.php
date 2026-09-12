<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Helpers\Ticket;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelStaleTickets
{
    use AsAction;

    public string $commandSignature = 'tickets:cancel_stale {--days=14 : days a ticket may wait for a reply}';

    public function handle(int $days = 14): int
    {
        $cancelled = 0;

        Ticket::where('status', TicketStatusEnum::WAITING)
            ->where('updated_at', '<', now()->subDays($days))
            ->cursor()
            ->each(function (Ticket $ticket) use ($days, &$cancelled) {
                $ticket->update([
                    'status'    => TicketStatusEnum::CANCELLED,
                    'closed_at' => now(),
                ]);
                $ticket->comments()->create([
                    'is_internal' => true,
                    'body'        => __('No reply for :days days', ['days' => $days]),
                ]);
                PostTicketSlackThreadReply::run($ticket, $ticket->reference.' is now '.TicketStatusEnum::labels()[$ticket->status->value]);
                SyncTicketSlackAlert::run($ticket);
                $cancelled++;
            });

        return $cancelled;
    }

    public function asCommand(Command $command): int
    {
        $command->info($this->handle((int) $command->option('days')).' tickets cancelled');

        return 0;
    }
}
