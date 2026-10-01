<?php

/*
 * Author Louis Perez
 * Created on 01-10-2026
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket\Json;

use App\Actions\OrgAction;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use Lorisleiva\Actions\ActionRequest;

class GetTicketRow extends OrgAction
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
        return TicketResource::make($ticket->load(['reporter', 'customer', 'assignee', 'collaborators', 'organisation', 'source', 'qaUser']))->toArray(request());
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
