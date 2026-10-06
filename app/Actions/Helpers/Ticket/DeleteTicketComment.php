<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketComment;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

class DeleteTicketComment extends OrgAction
{
    public function handle(TicketComment $ticketComment): void
    {
        $ticket = $ticketComment->ticket;
        $actor  = request()->user();

        if (!$ticketComment->isAuthoredBy($actor)) {
            $this->recordRemovalInHistory($ticket, $ticketComment);
        }

        $ticketComment->delete();

        NotifyTicketUsers::make()->pushBadges($ticket, $actor instanceof User ? $actor : null);
    }

    private function recordRemovalInHistory(Ticket $ticket, TicketComment $ticketComment): void
    {
        $author = $ticketComment->author;

        $ticket->auditEvent     = 'updated';
        $ticket->isCustomEvent  = true;
        $ticket->auditCustomOld = ['comment' => $author?->contact_name ?: $author?->username ?: __('Unknown')];
        $ticket->auditCustomNew = ['comment' => null];
        Event::dispatch(new AuditCustom($ticket));
        $ticket->isCustomEvent = false;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('ticketComment')->canBeDeletedBy($request->user());
    }

    public function asController(TicketComment $ticketComment, ActionRequest $request): void
    {
        $this->initialisationFromGroup($ticketComment->ticket->group, $request);
        $this->handle($ticketComment);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
