<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Ticket\UI;

use App\Actions\Helpers\Ticket\RateTicket;
use App\Actions\RetinaAction;
use App\Http\Resources\Helpers\TicketCommentResource;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaTicket extends RetinaAction
{
    private Ticket $ticket;

    public function authorize(ActionRequest $request): bool
    {
        return $this->ticket->customer_id === $this->customer->id;
    }

    public function asController(Ticket $ticket, ActionRequest $request): Ticket
    {
        $this->ticket = $ticket;
        $this->initialisation($request);

        return $ticket;
    }

    public function htmlResponse(Ticket $ticket): Response
    {
        return Inertia::render(
            'Dropshipping/RetinaTicket',
            [
                'breadcrumbs' => array_merge(
                    IndexRetinaTickets::make()->getBreadcrumbs(),
                    [['type' => 'simple', 'simple' => ['route' => ['name' => 'retina.dropshipping.tickets.show', 'parameters' => [$ticket->reference]], 'label' => $ticket->reference]]]
                ),
                'title'       => $ticket->reference,
                'pageHead'    => [
                    'title' => $ticket->reference,
                    'icon'  => 'fal fa-life-ring',
                ],
                'ticket'      => TicketResource::make($ticket)->toArray(request()),
                'comments'    => TicketCommentResource::collection(
                    $ticket->comments()->where('is_internal', false)->with('author')->orderBy('id')->get()
                )->toArray(request()),
                'can_rate'    => RateTicket::canRate($ticket, $this->webUser),
                'routes'      => [
                    'comment' => ['name' => 'retina.models.ticket.comment.store', 'parameters' => ['ticket' => $ticket->id]],
                    'rate'    => ['name' => 'retina.models.ticket.rate', 'parameters' => ['ticket' => $ticket->id]],
                ],
            ]
        );
    }
}
