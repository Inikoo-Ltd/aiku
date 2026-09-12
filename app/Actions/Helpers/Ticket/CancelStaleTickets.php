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

        $cutoff = now()->subDays($days);

        Ticket::where('status', TicketStatusEnum::WAITING)
            ->where(fn ($query) => $query
                ->where('waiting_until', '<', now())
                ->orWhere(fn ($query) => $query
                    ->whereNull('waiting_until')
                    ->where('created_at', '<', $cutoff)
                    ->whereDoesntHave('comments', fn ($query) => $query->where('is_internal', false)->where('created_at', '>=', $cutoff))))
            ->cursor()
            ->each(function (Ticket $ticket) use ($days, &$cancelled) {
                $days = $ticket->waiting_at && $ticket->waiting_until ? max(1, (int) round($ticket->waiting_at->diffInDays($ticket->waiting_until))) : $days;
                $ticket->update([
                    'status'        => TicketStatusEnum::CANCELLED,
                    'closed_at'     => now(),
                    'waiting_until' => null,
                ]);
                $ticket->comments()->create([
                    'is_internal' => true,
                    'body'        => __('No reply for :days days', ['days' => $days]),
                ]);
                PostTicketSlackThreadReply::run($ticket, $ticket->reference.' is now '.TicketStatusEnum::labels()[$ticket->status->value].': '.__('no reply for :days days. Reply here to reopen it', ['days' => $days]));
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
