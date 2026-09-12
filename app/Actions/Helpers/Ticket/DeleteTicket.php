<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 13 Sep 2026 00:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Models\Helpers\Ticket;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class DeleteTicket extends OrgAction
{
    public function handle(Ticket $ticket): void
    {
        $ticket->delete();
    }

    public function authorize(ActionRequest $request): bool
    {
        return Ticket::canBeAssignedBy($request->user());
    }

    public function asController(Ticket $ticket, ActionRequest $request): void
    {
        $this->initialisationFromGroup($ticket->group, $request);
        $this->handle($ticket);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->route('grp.tickets.index');
    }
}
