<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\Ticket\Concerns\WithTicketsWriteGuard;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateTicket extends OrgAction
{
    use WithTicketsWriteGuard;

    use WithActionUpdate;

    public function handle(Ticket $ticket, array $modelData): Ticket
    {
        $this->guardTicketsWritable($ticket->type);

        if ($status = Arr::get($modelData, 'status')) {
            $status = TicketStatusEnum::from($status);
            data_set($modelData, 'resolved_at', $status === TicketStatusEnum::RESOLVED ? now() : ($status->isOpen() ? null : $ticket->resolved_at));
            data_set($modelData, 'closed_at', $status === TicketStatusEnum::CANCELLED ? now() : null);
        }

        $ticket = $this->update($ticket, $modelData);

        if ($ticket->wasChanged('status')) {
            PostTicketSlackThreadReply::run($ticket, $ticket->reference.' is now '.TicketStatusEnum::labels()[$ticket->status->value]);
        }

        if ($ticket->wasChanged(['status', 'assignee_id'])) {
            SyncTicketSlackAlert::run($ticket);
        }

        if ($ticket->wasChanged('assignee_id') && ($actor = request()->user()) instanceof User) {
            $previous = $ticket->getOriginal('assignee_id') ? User::find($ticket->getOriginal('assignee_id')) : null;
            $ticket->comments()->create([
                'author_type' => 'User',
                'author_id'   => $actor->id,
                'is_internal' => true,
                'body'        => $ticket->assignee
                    ? ($previous ? __('Passed from :from to :to', ['from' => $previous->contact_name ?: $previous->username, 'to' => $ticket->assignee->contact_name ?: $ticket->assignee->username]) : __('Assigned to :to', ['to' => $ticket->assignee->contact_name ?: $ticket->assignee->username]))
                    : __('Unassigned'),
            ]);
        }

        if ($ticket->wasChanged('assignee_id') && $ticket->assignee_id && $conversation = $ticket->staffConversation) {
            $conversation->participants()->syncWithoutDetaching([$ticket->assignee_id]);
        }

        return $ticket;
    }

    public function rules(): array
    {
        return [
            'subject'     => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status'      => ['sometimes', Rule::enum(TicketStatusEnum::class)],
            'priority'    => ['sometimes', Rule::enum(ChatPriorityEnum::class)],
            'assignee_id' => ['sometimes', 'nullable', Rule::exists('users', 'id')->where('group_id', $this->group->id)],
            'kind'        => ['sometimes', 'nullable', Rule::enum(TicketKindEnum::class)],
            'module'      => ['sometimes', 'nullable', Rule::enum(TicketModuleEnum::class)],
            'tags'        => ['sometimes', 'array'],
            'is_confidential' => ['sometimes', 'boolean'],
            'tags.*'        => ['string', 'max:64'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || Ticket::canBeAssignedBy($request->user())) {
            return true;
        }

        $ticket = $request->route('ticket');
        if (Ticket::canBeManagedBy($request->user())) {
            $onOwnPlate = $ticket instanceof Ticket && $ticket->assignee_id === $request->user()->id && $request->filled('assignee_id');

            return (!$request->has('assignee_id') || $onOwnPlate) && !$request->has('is_confidential');
        }

        return $ticket instanceof Ticket && $ticket->isReportedBy($request->user()) && array_keys($request->all()) === ['status'];
    }

    public function action(Ticket $ticket, array $modelData): Ticket
    {
        $this->asAction = true;
        $this->initialisationFromGroup($ticket->group, $modelData);

        return $this->handle($ticket, $this->validatedData);
    }

    public function asController(Ticket $ticket, ActionRequest $request): Ticket
    {
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($ticket, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
