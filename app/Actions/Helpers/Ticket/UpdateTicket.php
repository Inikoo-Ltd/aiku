<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusGroupEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateTicket extends OrgAction
{
    use WithActionUpdate;

    private ?Ticket $updatingTicket = null;

    public function handle(Ticket $ticket, array $modelData): Ticket
    {
        $question      = trim((string) Arr::pull($modelData, 'question', ''));
        $statusComment = trim((string) Arr::pull($modelData, 'status_comment', ''));
        $waitingHours = Arr::pull($modelData, 'waiting_hours');

        $asker = auth()->user();
        if ($question !== '' && $asker instanceof User) {
            if (Arr::get($modelData, 'status') === TicketStatusEnum::PENDING_DEPLOY->value) {
                data_set($modelData, 'data', array_merge($ticket->data ?? [], ['deploy_comment' => ['body' => $question, 'user_id' => $asker->id]]));
            } else {
                StoreTicketComment::make()->action($ticket, $asker, ['body' => $question], notifyUsers: false);
            }
        }

        if ($statusComment !== '' && $asker instanceof User) {
            StoreTicketComment::make()->action($ticket, $asker, ['body' => $statusComment], notifyUsers: false);
        }

        if (Arr::exists($modelData, 'assignee_id') && Arr::get($modelData, 'assignee_id') != $ticket->assignee_id) {
            data_set($modelData, 'assigned_at', Arr::get($modelData, 'assignee_id') ? now() : null);

            if (!Arr::has($modelData, 'status') && in_array($ticket->status, [TicketStatusEnum::OPEN, TicketStatusEnum::ASSIGNED], true)) {
                data_set($modelData, 'status', Arr::get($modelData, 'assignee_id') ? TicketStatusEnum::ASSIGNED->value : TicketStatusEnum::OPEN->value);
            }
        }

        if ($status = Arr::get($modelData, 'status')) {
            $status = TicketStatusEnum::from($status);

            $assignee = Arr::exists($modelData, 'assignee_id') ? Arr::get($modelData, 'assignee_id') : $ticket->assignee_id;

            if ($status === TicketStatusEnum::ASSIGNED && !$assignee) {
                $status = TicketStatusEnum::OPEN;
                data_set($modelData, 'status', $status->value);
            }

            if ($status === TicketStatusEnum::OPEN && $assignee) {
                $status = TicketStatusEnum::ASSIGNED;
                data_set($modelData, 'status', $status->value);
            }

            data_set($modelData, 'assigned_at', $status === TicketStatusEnum::OPEN ? null : (Arr::get($modelData, 'assigned_at') ?? $ticket->assigned_at ?? now()));
            data_set($modelData, 'waiting_at', in_array($status, [TicketStatusEnum::WAITING, TicketStatusEnum::ANSWERED], true) ? ($ticket->status === $status ? $ticket->waiting_at : now()) : null);
            data_set($modelData, 'waiting_until', $status === TicketStatusEnum::WAITING
                ? ($waitingHours ? now()->addHours((int) $waitingHours) : ($ticket->waiting_until ?? now()->addHours($ticket->defaultWaitingHours())))
                : null);
            data_set($modelData, 'started_at', $status->group() === TicketStatusGroupEnum::TODO ? null : ($ticket->started_at ?? now()));
            data_set($modelData, 'resolved_at', $status === TicketStatusEnum::RESOLVED ? now() : ($status->isOpen() ? null : $ticket->resolved_at));
            data_set($modelData, 'closed_at', $status->isOpen() ? null : now());
        }

        $qaNote = trim((string) Arr::pull($modelData, 'qa_note', ''));
        if (Arr::exists($modelData, 'qa_status')) {
            $qaStatus = Arr::get($modelData, 'qa_status') ? TicketQaStatusEnum::from(Arr::get($modelData, 'qa_status')) : null;
            $isVerdict = in_array($qaStatus, [TicketQaStatusEnum::PASSED, TicketQaStatusEnum::FAILED], true);
            data_set($modelData, 'qa_requested_at', $qaStatus === TicketQaStatusEnum::REQUESTED ? now() : ($qaStatus ? $ticket->qa_requested_at : null));
            data_set($modelData, 'qa_checked_at', $isVerdict ? now() : null);
            data_set($modelData, 'qa_user_id', match (true) {
                $isVerdict && $asker instanceof User => $asker->id,
                $qaStatus === TicketQaStatusEnum::REQUESTED => Arr::get($modelData, 'qa_user_id'),
                default => null,
            });
        }

        $ticket = $this->update($ticket, $modelData);

        if ($ticket->wasChanged('qa_status') && $asker instanceof User) {
            $verdict = $ticket->qa_status ? TicketQaStatusEnum::labels()[$ticket->qa_status->value] : __('QA check withdrawn');
            $ticket->comments()->create([
                'author_type' => 'User',
                'author_id'   => $asker->id,
                'body'        => $qaNote !== '' ? $verdict.': '.$qaNote : $verdict,
            ]);
            PostTicketSlackThreadReply::run($ticket, $ticket->reference.' · '.$verdict);
        }

        if ($question !== '' && $asker instanceof User && $ticket->status === TicketStatusEnum::WAITING) {
            NotifyTicketUsers::make()->asked($ticket, $asker, $question);
        }

        if ($ticket->wasChanged('qa_status') && $asker instanceof User) {
            NotifyTicketUsers::make()->qaChanged($ticket, $asker);
        }

        if ($ticket->wasChanged('status') && $ticket->status === TicketStatusEnum::RESOLVED) {
            NotifyTicketUsers::make()->done($ticket, $asker instanceof User ? $asker : null);
        }

        if ($ticket->wasChanged('status')) {
            PostTicketSlackThreadReply::run($ticket, $ticket->reference.' is now '.TicketStatusEnum::labels()[$ticket->status->value]);
        }

        if ($ticket->wasChanged(['status', 'assignee_id'])) {
            SyncTicketSlackAlert::run($ticket);
        }

        if ($ticket->wasChanged('assignee_id') && $ticket->assignee_id) {
            $ticket->collaborators()->detach($ticket->assignee_id);
        }

        NotifyTicketUsers::make()->pushBadges($ticket, $asker instanceof User ? $asker : null);

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
            'kind'        => [
                'sometimes',
                'nullable',
                Rule::enum(TicketKindEnum::class),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $isEscalated = $this->updatingTicket?->kind === TicketKindEnum::ESCALATION;

                    if ($isEscalated !== ($value === TicketKindEnum::ESCALATION->value)) {
                        $fail($isEscalated ? __('Escalated tickets keep their kind.') : __('Tickets cannot be changed to escalated.'));
                    }
                },
            ],
            'module'      => ['sometimes', 'nullable', Rule::enum(TicketModuleEnum::class)],
            'tags'        => ['sometimes', 'array'],
            'is_confidential' => ['sometimes', 'boolean'],
            'question'      => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status_comment' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'qa_status'     => ['sometimes', 'nullable', Rule::enum(TicketQaStatusEnum::class)],
            'qa_note'       => ['sometimes', 'nullable', 'string', 'max:10000'],
            'qa_user_id'    => ['sometimes', 'nullable', Rule::in(GetTicketBadgeData::qaUsers($this->group->id)->pluck('id'))],
            'waiting_hours' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:720'],
            'tags.*'        => ['string', 'max:64'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || Ticket::canBeAssignedBy($request->user())) {
            return true;
        }

        $user   = $request->user();
        $ticket = $request->route('ticket');
        if (!$ticket instanceof Ticket) {
            return false;
        }

        $fields = array_keys($request->all());

        if ($request->has('qa_status')) {
            if (array_diff($fields, ['qa_status', 'qa_note', 'qa_user_id']) !== []) {
                return false;
            }

            $isVerdict = in_array($request->input('qa_status'), [TicketQaStatusEnum::PASSED->value, TicketQaStatusEnum::FAILED->value], true);

            return $isVerdict ? Ticket::canCheckQa($user) : $ticket->canContributeBy($user);
        }

        if ($ticket->canBeUpdatedBy($user)) {
            return (!$request->has('assignee_id') || $request->filled('assignee_id')) && !$request->has('is_confidential');
        }

        if ($request->has('tags') && array_diff($fields, ['tags']) === [] && $ticket->hasCollaborator($user)) {
            return true;
        }

        return $ticket->isReportedBy($user)
            && $request->has('status')
            && array_diff($fields, ['status', 'status_comment']) === [];
    }

    public function action(Ticket $ticket, array $modelData): Ticket
    {
        $this->asAction       = true;
        $this->updatingTicket = $ticket;
        $this->initialisationFromGroup($ticket->group, $modelData);

        return $this->handle($ticket, $this->validatedData);
    }

    public function asController(Ticket $ticket, ActionRequest $request): Ticket
    {
        $this->updatingTicket = $ticket;
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($ticket, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
