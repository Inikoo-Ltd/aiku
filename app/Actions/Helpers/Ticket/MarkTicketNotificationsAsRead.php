<?php

/*
 * Author Louis Perez
 * Created on 15-09-2026-14h-11m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket;

use App\Events\BroadcastRetinaTicketBadgeUpdate;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkTicketNotificationsAsRead
{
    use AsAction;

    public function handle(Ticket $ticket, User|WebUser $viewer): int
    {
        $markedCount = $viewer->unreadNotifications()
            ->where(fn ($notifications) => $notifications
                ->whereRaw("(data::jsonb)->>'ticket_id' = ?", [(string) $ticket->id])
                ->orWhereRaw("(data::jsonb)->>'route' = ?", [route('grp.tickets.show', $ticket->reference)]))
            ->update(['read_at' => now()]);

        if ($markedCount > 0) {
            if ($viewer instanceof WebUser) {
                BroadcastRetinaTicketBadgeUpdate::dispatch($viewer->id, GetRetinaTicketBadgeData::run($viewer));
            } else {
                SendTicketBadgeUpdateToUsers::run([$viewer->id]);
            }
        }

        return $markedCount;
    }
}
