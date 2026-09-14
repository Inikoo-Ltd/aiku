<?php

/*
 * Author Louis Perez
 * Created on 14-09-2026-16h-25m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket\Json;

use App\Actions\Helpers\Ticket\UI\ShowTicket;
use App\Actions\OrgAction;
use App\Models\Helpers\Ticket;
use Lorisleiva\Actions\ActionRequest;

class GetTicketControls extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(Ticket $ticket): array
    {
        return ShowTicket::make()->controlProps($ticket);
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(Ticket $ticket, ActionRequest $request): array
    {
        abort_unless($ticket->isVisibleTo($request->user()), 403);
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($ticket);
    }
}
