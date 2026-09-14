<?php

/*
 * Author Louis Perez
 * Created on 14-09-2026-17h-08m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket;

use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Helpers\Ticket;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveTicketsWaitingForDeployment
{
    use AsAction;

    public function handle(): int
    {
        $resolved = 0;

        Ticket::where('is_waiting_for_deployment', true)
            ->cursor()
            ->each(function (Ticket $ticket) use (&$resolved) {
                UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);
                $resolved++;
            });

        return $resolved;
    }
}
