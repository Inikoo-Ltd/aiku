<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Models\Helpers\TicketComment;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class DeleteTicketComment extends OrgAction
{
    public function handle(TicketComment $ticketComment): void
    {
        $ticketComment->delete();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('ticketComment')->isAuthoredBy($request->user());
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
