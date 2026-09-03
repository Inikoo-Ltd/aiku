<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Ticket;

use App\Actions\Helpers\Ticket\StoreTicketComment;
use App\Actions\RetinaAction;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketComment;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaTicketComment extends RetinaAction
{
    private Ticket $ticket;

    public function handle(Ticket $ticket, array $modelData): TicketComment
    {
        return StoreTicketComment::make()->action($ticket, $this->webUser, ['body' => $modelData['body']]);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->ticket->customer_id === $this->customer->id;
    }

    public function asController(Ticket $ticket, ActionRequest $request): TicketComment
    {
        $this->ticket = $ticket;
        $this->initialisation($request);

        return $this->handle($ticket, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
