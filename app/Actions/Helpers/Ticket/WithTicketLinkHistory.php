<?php

/*
 * Author Louis Perez
 * Created on 07-10-2026-13h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket;

use App\Models\Helpers\Ticket;
use OwenIt\Auditing\Facades\Auditor;

trait WithTicketLinkHistory
{
    /**
     * Keyed by the other ticket: changes by one user within seconds are merged into one audit,
     * so links added together must not share a key or the later would overwrite the earlier.
     */
    private function recordLinkHistory(Ticket $ticket, Ticket $other, ?string $before, ?string $after): void
    {
        $ticket->auditEvent     = 'updated';
        $ticket->isCustomEvent  = true;
        $ticket->auditCustomOld = ['link_'.$other->id => $before];
        $ticket->auditCustomNew = ['link_'.$other->id => $after];
        Auditor::execute($ticket);
        $ticket->isCustomEvent = false;
    }
}
