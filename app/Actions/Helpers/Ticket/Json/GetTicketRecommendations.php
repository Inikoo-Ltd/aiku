<?php

/*
 * Author Louis Perez
 * Created on 17-09-2026-13h-23m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket\Json;

use App\Actions\Helpers\Ticket\Recommendations\RelatedTicketFinder;
use App\Actions\Helpers\Ticket\SuggestTicketArticles;
use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

class GetTicketRecommendations extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    /**
     * @return array{related_tickets: array<int, array<string, mixed>>, articles: array<int, array<string, string>>}
     */
    public function handle(Ticket $ticket, User $viewer): array
    {
        return [
            'related_tickets' => app(RelatedTicketFinder::class)
                ->find($ticket, $viewer)
                ->map(fn (Ticket $related) => [
                    'id'           => $related->id,
                    'reference'    => $related->reference,
                    'subject'      => $related->subject,
                    'status_label' => TicketStatusEnum::labels()[$related->status->value],
                    'status_icon'  => TicketStatusEnum::stateIcon()[$related->status->value],
                    'type_icon'    => $related->type?->icon(),
                ])
                ->values()
                ->all(),
            'articles'        => SuggestTicketArticles::run($ticket),
        ];
    }

    /**
     * @return array{related_tickets: array<int, array<string, mixed>>, articles: array<int, array<string, string>>}
     */
    public function asController(Ticket $ticket, ActionRequest $request): array
    {
        abort_unless($ticket->isVisibleTo($request->user()), 403);
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($ticket, $request->user());
    }
}
