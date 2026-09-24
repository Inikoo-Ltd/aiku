<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Models\Helpers\TicketComment;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class UpdateTicketComment extends OrgAction
{
    public function handle(TicketComment $ticketComment, array $modelData): TicketComment
    {
        $ticketComment->update($modelData);

        $actor = request()->user();
        NotifyTicketUsers::make()->pushBadges($ticketComment->ticket, $actor instanceof User ? $actor : null);

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
        if ($this->asAction) {
            return true;
        }

        return $request->route('ticketComment')->isAuthoredBy($request->user());
    }

    public function action(TicketComment $ticketComment, array $modelData): TicketComment
    {
        $this->asAction = true;
        $this->initialisationFromGroup($ticketComment->ticket->group, $modelData);

        return $this->handle($ticketComment, $this->validatedData);
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
