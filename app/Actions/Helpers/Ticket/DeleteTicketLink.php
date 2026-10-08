<?php

/*
 * Author Louis Perez
 * Created on 07-10-2026-13h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Models\Helpers\TicketLink;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class DeleteTicketLink extends OrgAction
{
    use WithTicketLinkHistory;

    private ?TicketLink $removingLink = null;

    public function handle(TicketLink $ticketLink): void
    {
        $from = $ticketLink->ticket;
        $to   = $ticketLink->linkedTicket;

        $ticketLink->delete();

        $this->recordLinkHistory($from, $to, $ticketLink->type->outwardLabel().' '.$to->reference, null);
        $this->recordLinkHistory($to, $from, $ticketLink->type->inwardLabel().' '.$from->reference, null);

        $from->broadcastUpdated();
        $to->broadcastUpdated();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $user = $request->user();

        return $this->removingLink !== null
            && ($this->removingLink->ticket->canLinkBy($user) || $this->removingLink->linkedTicket->canLinkBy($user));
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function asController(TicketLink $ticketLink, ActionRequest $request): void
    {
        $this->removingLink = $ticketLink;
        $this->initialisationFromGroup($ticketLink->ticket->group, $request);

        $this->handle($ticketLink);
    }
}
