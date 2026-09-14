<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketComment;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class ToggleTicketCommentVisibility extends OrgAction
{
    public function handle(TicketComment $ticketComment): TicketComment
    {
        $ticketComment->update(['is_internal' => !$ticketComment->is_internal]);

        $actor = request()->user();
        NotifyTicketUsers::make()->pushBadges($ticketComment->ticket, $actor instanceof User ? $actor : null);

        return $ticketComment;
    }

    public function authorize(ActionRequest $request): bool
    {
        return Ticket::canBeAssignedBy($request->user());
    }

    public function asController(TicketComment $ticketComment, ActionRequest $request): TicketComment
    {
        $this->initialisationFromGroup($ticketComment->ticket->group, $request);

        return $this->handle($ticketComment);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
