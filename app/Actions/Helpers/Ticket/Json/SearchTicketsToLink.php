<?php

/*
 * Author Louis Perez
 * Created on 07-10-2026-13h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket\Json;

use App\Actions\Helpers\Ticket\ApplyTicketSearch;
use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

class SearchTicketsToLink extends OrgAction
{
    private const int LIMIT = 10;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    /**
     * Tickets the viewer can see matching a reference or words, without this ticket and those already linked to it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(Ticket $ticket, User $viewer, string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }

        $linkedIds = $ticket->outgoingLinks()->pluck('linked_ticket_id')
            ->merge($ticket->incomingLinks()->pluck('ticket_id'))
            ->push($ticket->id)
            ->all();

        $query = Ticket::query()
            ->where('tickets.group_id', $ticket->group_id)
            ->visibleTo($viewer)
            ->whereNotIn('tickets.id', $linkedIds)
            ->select('tickets.*');

        ApplyTicketSearch::run($query, $search, $viewer);

        return $query->limit(self::LIMIT)->get()
            ->map(fn (Ticket $found) => [
                'id'           => $found->id,
                'reference'    => $found->reference,
                'subject'      => $found->subject,
                'status_label' => TicketStatusEnum::labels()[$found->status->value],
                'status_icon'  => TicketStatusEnum::stateIcon()[$found->status->value],
                'type_icon'    => $found->type?->icon(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function asController(Ticket $ticket, ActionRequest $request): array
    {
        abort_unless($ticket->isVisibleTo($request->user()), 403);
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($ticket, $request->user(), (string) $request->query('search', ''));
    }
}
