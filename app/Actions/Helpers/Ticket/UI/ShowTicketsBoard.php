<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket\UI;

use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTicketsBoard extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    public function handle(Group $group): array
    {
        $tickets = Ticket::where('group_id', $group->id)
            ->where(fn ($query) => $query->where('status', '!=', TicketStatusEnum::CLOSED)->orWhere('closed_at', '>=', now()->subDays(7)))
            ->with(['reporter', 'assignee', 'customer'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy(fn (Ticket $ticket) => $ticket->status->value);

        return collect(TicketStatusEnum::cases())->map(fn (TicketStatusEnum $status) => [
            'status'  => $status->value,
            'label'   => TicketStatusEnum::labels()[$status->value],
            'icon'    => TicketStatusEnum::stateIcon()[$status->value],
            'tickets' => TicketResource::collection($tickets->get($status->value, collect()))->toArray(request()),
        ])->values()->all();
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group);
    }

    public function htmlResponse(array $columns): Response
    {
        return Inertia::render(
            'Tickets/TicketsBoard',
            [
                'breadcrumbs' => array_merge(
                    IndexTickets::make()->getBreadcrumbs(),
                    [['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.tickets.board'], 'label' => __('Board')]]]
                ),
                'title'       => __('Tickets board'),
                'pageHead'    => [
                    'title'   => __('Tickets board'),
                    'icon'    => ['fal', 'fa-columns'],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('New ticket'),
                            'route' => ['name' => 'grp.tickets.create'],
                        ],
                    ],
                ],
                'columns'     => $columns,
                'updateRoute' => 'grp.models.ticket.update',
            ]
        );
    }
}
