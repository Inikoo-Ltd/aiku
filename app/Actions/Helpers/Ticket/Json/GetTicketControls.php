<?php

/*
 * Author Louis Perez
 * Created on 14-09-2026-16h-25m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket\Json;

use App\Actions\Helpers\Ticket\MarkTicketNotificationsAsRead;
use App\Actions\Helpers\Ticket\UI\ShowTicket;
use App\Actions\OrgAction;
use App\Http\Resources\Helpers\TicketCommentResource;
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
        return [
            ...ShowTicket::make()->controlProps($ticket),
            'comments'              => TicketCommentResource::collection($ticket->commentsVisibleTo(request()->user())->with('author')->orderByDesc('id')->get())->toArray(request()),
            'comments_newest_first' => (bool) data_get(request()->user()->settings, 'ticket_comments_newest_first', true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(Ticket $ticket, ActionRequest $request): array
    {
        abort_unless($ticket->isVisibleTo($request->user()), 403);
        $this->initialisationFromGroup($ticket->group, $request);
        MarkTicketNotificationsAsRead::run($ticket, $request->user());

        return $this->handle($ticket);
    }
}
