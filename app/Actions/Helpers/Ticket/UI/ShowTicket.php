<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket\UI;

use App\Actions\Helpers\Ticket\RateTicket;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Http\Resources\Helpers\TicketCommentResource;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTicket extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    public function handle(Ticket $ticket): Ticket
    {
        return $ticket;
    }

    public function asController(Ticket $ticket, ActionRequest $request): Ticket
    {
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($ticket);
    }

    public function htmlResponse(Ticket $ticket): Response
    {
        return Inertia::render(
            'Tickets/Ticket',
            [
                'breadcrumbs' => $this->getBreadcrumbs($ticket),
                'title'       => $ticket->reference,
                'pageHead'    => [
                    'model' => __('Ticket'),
                    'title' => $ticket->reference,
                    'icon'  => ['fal', 'fa-life-ring'],
                ],
                'ticket'      => TicketResource::make($ticket)->toArray(request()),
                'comments'    => TicketCommentResource::collection($ticket->comments()->with('author')->orderBy('id')->get())->toArray(request()),
                'options'     => [
                    'statuses'   => collect(TicketStatusEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                    'priorities' => collect(ChatPriorityEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                    'assignees'  => User::where('group_id', $ticket->group_id)->where('status', true)->orderBy('username')->get(['id', 'username', 'contact_name'])
                        ->map(fn (User $user) => ['label' => $user->contact_name ?: $user->username, 'value' => $user->id])->values(),
                ],
                'can_rate'    => RateTicket::canRate($ticket, request()->user()),
                'routes'      => [
                    'update'  => ['name' => 'grp.models.ticket.update', 'parameters' => ['ticket' => $ticket->id]],
                    'comment' => ['name' => 'grp.models.ticket.comment.store', 'parameters' => ['ticket' => $ticket->id]],
                    'rate'    => ['name' => 'grp.models.ticket.rate', 'parameters' => ['ticket' => $ticket->id]],
                ],
            ]
        );
    }

    public function getBreadcrumbs(Ticket $ticket): array
    {
        return array_merge(
            IndexTickets::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => ['name' => 'grp.tickets.show', 'parameters' => [$ticket->reference]],
                        'label' => $ticket->reference,
                    ],
                ],
            ]
        );
    }
}
