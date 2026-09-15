<?php

/*
 * Author Louis Perez
 * Created on 15-09-2026-14h-11m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket;

use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkTicketNotificationsAsRead
{
    use AsAction;

    public function handle(Ticket $ticket, User $user): int
    {
        $markedCount = $user->unreadNotifications()
            ->whereRaw("(data::jsonb)->>'route' = ?", [route('grp.tickets.show', $ticket->reference)])
            ->update(['read_at' => now()]);

        if ($markedCount > 0) {
            SendTicketBadgeUpdateToUsers::run([$user->id]);
        }

        return $markedCount;
    }
}
