<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class EscalateTicket extends OrgAction
{
    public function handle(Ticket $customerTicket, User $user, array $modelData = []): Ticket
    {
        return StoreTicket::make()->action($customerTicket->group, [
            'type'            => TicketTypeEnum::HELP->value,
            'kind'            => TicketKindEnum::ESCALATION->value,
            'subject'         => Arr::get($modelData, 'subject', $customerTicket->subject),
            'description'     => Arr::get($modelData, 'description', $customerTicket->description),
            'priority'        => $customerTicket->priority->value,
            'organisation_id' => $customerTicket->organisation_id,
            'shop_id'         => $customerTicket->shop_id,
            'customer_id'     => $customerTicket->customer_id,
            'reporter_type'   => 'User',
            'reporter_id'     => $user->id,
            'model_type'      => 'Ticket',
            'model_id'        => $customerTicket->id,
        ]);
    }

    public function rules(): array
    {
        return [
            'subject'     => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return Ticket::canBeManagedBy($request->user());
    }

    public function asController(Ticket $ticket, ActionRequest $request): Ticket
    {
        abort_unless($ticket->type === TicketTypeEnum::CUSTOMER, 422, 'Only customer tickets can be escalated');
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($ticket, $request->user(), $this->validatedData);
    }

    public function htmlResponse(Ticket $ticket): RedirectResponse
    {
        return redirect()->route('grp.tickets.show', $ticket->reference);
    }
}
