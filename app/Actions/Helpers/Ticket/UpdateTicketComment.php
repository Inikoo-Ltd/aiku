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

class UpdateTicketComment extends OrgAction
{
    public function handle(TicketComment $ticketComment, array $modelData): TicketComment
    {
        $ticketComment->update($modelData);

        return $ticketComment;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('ticketComment')->isAuthoredBy($request->user());
    }

    public function asController(TicketComment $ticketComment, ActionRequest $request): TicketComment
    {
        $this->initialisationFromGroup($ticketComment->ticket->group, $request);

        return $this->handle($ticketComment, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
