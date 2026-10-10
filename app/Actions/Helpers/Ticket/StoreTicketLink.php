<?php

/*
 * Author Louis Perez
 * Created on 07-10-2026-13h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketLinkTypeEnum;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketLink;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreTicketLink extends OrgAction
{
    use WithTicketLinkHistory;

    private ?Ticket $linkingTicket = null;

    private const array CHOICES = [
        'relates'       => [TicketLinkTypeEnum::RELATES, false],
        'blocks'        => [TicketLinkTypeEnum::BLOCKS, false],
        'blocked_by'    => [TicketLinkTypeEnum::BLOCKS, true],
        'duplicates'    => [TicketLinkTypeEnum::DUPLICATES, false],
        'duplicated_by' => [TicketLinkTypeEnum::DUPLICATES, true],
    ];

    /**
     * @param array{linked_ticket_id: int, type: string} $modelData
     */
    public function handle(Ticket $ticket, array $modelData, ?User $actor = null): TicketLink
    {
        $other = Ticket::where('group_id', $ticket->group_id)->find($modelData['linked_ticket_id']);

        if (!$other || $other->id === $ticket->id || ($actor && !$other->isVisibleTo($actor))) {
            throw ValidationException::withMessages(['linked_ticket_id' => __('This ticket can not be linked')]);
        }

        $alreadyLinked = TicketLink::where(fn ($query) => $query->where('ticket_id', $ticket->id)->where('linked_ticket_id', $other->id))
            ->orWhere(fn ($query) => $query->where('ticket_id', $other->id)->where('linked_ticket_id', $ticket->id))
            ->exists();

        if ($alreadyLinked) {
            throw ValidationException::withMessages(['linked_ticket_id' => __(':reference is already linked', ['reference' => $other->reference])]);
        }

        [$type, $isInward] = self::CHOICES[$modelData['type']];
        [$from, $to]       = $isInward ? [$other, $ticket] : [$ticket, $other];

        $link = TicketLink::create([
            'group_id'         => $ticket->group_id,
            'ticket_id'        => $from->id,
            'linked_ticket_id' => $to->id,
            'type'             => $type,
            'created_by_id'    => $actor?->id,
        ]);

        $this->recordLinkHistory($from, $to, null, $type->outwardLabel().' '.$to->reference);
        $this->recordLinkHistory($to, $from, null, $type->inwardLabel().' '.$from->reference);

        $from->broadcastUpdated();
        $to->broadcastUpdated();

        return $link;
    }

    public function rules(): array
    {
        return [
            'linked_ticket_id' => ['required', 'integer'],
            'type'             => ['required', 'string', Rule::in(array_keys(self::CHOICES))],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->asAction || ($this->linkingTicket?->canLinkBy($request->user()) ?? false);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    /**
     * @param array{linked_ticket_id: int, type: string} $modelData
     */
    public function action(Ticket $ticket, array $modelData, ?User $actor = null): TicketLink
    {
        $this->asAction      = true;
        $this->linkingTicket = $ticket;
        $this->initialisationFromGroup($ticket->group, Arr::only($modelData, ['linked_ticket_id', 'type']));

        return $this->handle($ticket, $this->validatedData, $actor);
    }

    public function asController(Ticket $ticket, ActionRequest $request): TicketLink
    {
        $this->linkingTicket = $ticket;
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($ticket, $this->validatedData, $request->user());
    }
}
