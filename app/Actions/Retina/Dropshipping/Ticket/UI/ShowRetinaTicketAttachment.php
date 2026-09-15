<?php

/*
 * Author Louis Perez
 * Created on 14-09-2026-15h-01m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Retina\Dropshipping\Ticket\UI;

use App\Actions\Helpers\Ticket\UI\ShowTicketAttachment;
use App\Actions\RetinaAction;
use App\Models\Helpers\Media;
use App\Models\Helpers\Ticket;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class ShowRetinaTicketAttachment extends RetinaAction
{
    private Ticket $ticket;

    public function authorize(ActionRequest $request): bool
    {
        return $this->ticket->customer_id === $this->customer->id;
    }

    public function asController(Ticket $ticket, Media $media, ActionRequest $request): Response
    {
        $this->ticket = $ticket;
        $this->initialisation($request);
        abort_unless($ticket->hasAttachmentVisibleTo($media, $this->webUser), 404);

        return ShowTicketAttachment::make()->handle($media, $request->boolean('contents'));
    }
}
