<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Chat\EndTicketConversation;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketCommentTypeEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusGroupEnum;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketComment;
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
        $deployCommit = strtolower(trim((string) Arr::pull($modelData, 'deploy_commit', '')));
        $images = Arr::pull($modelData, 'images', []);
        $reopensOnQaFailure = (bool) Arr::pull($modelData, 'reopen', true);

        $asker = auth()->user();
        if ($question !== '' && $asker instanceof User) {
            if (Arr::get($modelData, 'status') === TicketStatusEnum::PENDING_DEPLOY->value) {
                TicketComment::where('ticket_id', $ticket->id)->where('type', TicketCommentTypeEnum::WAITING_FOR_DEPLOYMENT)->get()->each->delete();
                TicketComment::create([
                    'ticket_id'   => $ticket->id,
                    'author_type' => 'User',
                    'author_id'   => $asker->id,
                    'body'        => $question,
                    'type'        => TicketCommentTypeEnum::WAITING_FOR_DEPLOYMENT,
                ])->attachTicketImages($images);
            } else {
                StoreTicketComment::make()->action($ticket, $asker, ['body' => $question, 'images' => $images], isStatusNote: true);
            }
        }

        if (Arr::get($modelData, 'status') === TicketStatusEnum::PENDING_DEPLOY->value) {
            $data = Arr::except(Arr::get($modelData, 'data', $ticket->data ?? []), 'deploy_commit');
            data_set($modelData, 'data', $deployCommit === '' ? $data : array_merge($data, ['deploy_commit' => $deployCommit]));
        }

        if ($statusComment !== '' && $asker instanceof User) {
            StoreTicketComment::make()->action($ticket, $asker, ['body' => $statusComment, 'images' => $images], isStatusNote: Arr::get($modelData, 'status') !== TicketStatusEnum::ANSWERED->value);
        }

        /* A failed check on a ticket already marked Done sends it back to the assignee: the work
           is not finished, and a ticket left Closed drops off the board where nobody looks at it
           again. Only from Done - failing a ticket that is still in progress changes nothing,
           since it is already where it needs to be - and only when the caller is not setting a
           status itself, so an explicit choice always wins, and only when QA left "Reopen ticket
           back" ticked. Passing and skipping never move a
           ticket. The status block below does the rest: it clears resolved_at and closed_at and
           restores started_at, and the usual status notifications go out. */
        if (Arr::get($modelData, 'qa_status') === TicketQaStatusEnum::FAILED->value
            && $reopensOnQaFailure
            && $ticket->status === TicketStatusEnum::RESOLVED
            && !Arr::exists($modelData, 'status')
        ) {
            data_set($modelData, 'status', TicketStatusEnum::IN_PROGRESS->value);
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
            data_set($modelData, 'data', Arr::except(Arr::get($modelData, 'data', $ticket->data ?? []), Ticket::CANCELLED_FOR_NO_REPLY));
        }

        /* Whatever was said when the QA status changed, from either side: the assignee's note
           when asking for a check, and QA's own when passing, failing or skipping. One field rather
           than two, because the comment it posts is labelled with the status - "QA check
           requested: ..." against the assignee's name, "QA failed: ..." against QA's - so who said
           what is never in doubt. A verdict needs no request first. authorize() below keeps
           verdicts to QA and asking (or withdrawing) to the assignee, collaborators
           or whoever can assign. */
        $qaNote = trim((string) Arr::pull($modelData, 'qa_note', ''));
        if (Arr::exists($modelData, 'qa_status')) {
            $qaStatus = Arr::get($modelData, 'qa_status') ? TicketQaStatusEnum::from(Arr::get($modelData, 'qa_status')) : null;
            $isVerdict = (bool) $qaStatus?->isVerdict();
            data_set($modelData, 'qa_requested_at', $qaStatus === TicketQaStatusEnum::REQUESTED ? now() : ($qaStatus ? $ticket->qa_requested_at : null));
            data_set($modelData, 'qa_checked_at', $isVerdict ? now() : null);
            $askedQaUserIds = array_values(array_unique(array_map('intval', array_filter([...Arr::pull($modelData, 'qa_user_ids', []), Arr::pull($modelData, 'qa_user_id')]))));
            data_set($modelData, 'qa_user_id', ($isVerdict || $qaStatus === TicketQaStatusEnum::CHECKING) && $asker instanceof User ? $asker->id : null);
            data_set($modelData, 'qa_user_ids', match (true) {
                $qaStatus === TicketQaStatusEnum::REQUESTED => $askedQaUserIds,
                $qaStatus === null => [],
                default => $ticket->qa_user_ids ?? [],
            });
        }

        $ticket = $this->update($ticket, $modelData);

        if ($ticket->wasChanged('qa_status') && $asker instanceof User) {
            $verdict = $ticket->qa_status ? TicketQaStatusEnum::labels()[$ticket->qa_status->value] : __('QA check withdrawn');
            $ticket->comments()->create([
                'author_type' => 'User',
                'author_id'   => $asker->id,
                'body'        => $qaNote !== '' ? $verdict.': '.$qaNote : $verdict,
                /* Only a verdict marks the comment. Requesting a check, and withdrawing one,
                   are ordinary comments: there is nothing to show a badge for yet. */
                'has_qa_verdict' => $ticket->qa_status?->isVerdict() ? $ticket->qa_status->value : null,
            ])->attachTicketImages($images);
            NotifyTicketUsers::make()->mentioned($ticket, $asker, $qaNote);
            PostTicketSlackThreadReply::dispatch($ticket, $ticket->reference.' · '.$verdict);
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

        // Settled, and the reporter asked for the customer to be told: the closing note goes to
        // them in the conversation it came from, and that conversation ends.
        if ($ticket->closes_source
            && $statusComment !== ''
            && $ticket->wasChanged('status')
            && in_array($ticket->status, [TicketStatusEnum::RESOLVED, TicketStatusEnum::CANCELLED], true)
        ) {
            $this->tellTheCustomer($ticket, $statusComment, $asker instanceof User ? $asker : null);
        }

        if ($ticket->wasChanged('status') && $this->reporterHearsAboutStatus($ticket, $asker instanceof User ? $asker : null)) {
            NotifyTicketUsers::make()->statusChanged($ticket, $asker instanceof User ? $asker : null);
        }

        if ($ticket->wasChanged('status')) {
            NotifyTicketUsers::make()->statusChangedForCollaborators($ticket, $asker instanceof User ? $asker : null);
        }

        $editedFields = array_values(array_filter(['priority', 'module', 'kind', 'description'], fn (string $field) => $ticket->wasChanged($field)));
        if ($editedFields !== []) {
            NotifyTicketUsers::make()->edited($ticket, $asker instanceof User ? $asker : null, $editedFields);
        }

        if ($ticket->wasChanged('status')) {
            PostTicketSlackThreadReply::dispatch($ticket, $ticket->reference.' is now '.TicketStatusEnum::labels()[$ticket->status->value]);
        }

        if ($ticket->wasChanged(['status', 'assignee_id'])) {
            SyncTicketSlackAlert::dispatch($ticket);
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
            'reporter_muted' => ['sometimes', 'boolean'],
            'question'      => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status_comment' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'qa_status'     => [
                'sometimes',
                'nullable',
                Rule::enum(TicketQaStatusEnum::class),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === TicketQaStatusEnum::SKIPPED->value && $this->updatingTicket?->qa_requested_at !== null) {
                        $fail(__('QA was asked to check this ticket, so it cannot be skipped.'));
                    }

                    $qaStatus = TicketQaStatusEnum::tryFrom((string) $value);
                    $current  = $this->updatingTicket;
                    $user     = request()->user();

                    $incomingStatus = TicketStatusEnum::tryFrom((string) $this->get('status'));
                    if ($qaStatus && $current && ($incomingStatus ? $incomingStatus->group() === TicketStatusGroupEnum::TODO : !$current->isReadyForQa())) {
                        $fail(__('QA can check this ticket once it is in progress.'));

                        return;
                    }

                    $isRecheck = $qaStatus === TicketQaStatusEnum::CHECKING && $current?->qa_status?->canBeCheckedAgain();

                    if (($qaStatus?->isVerdict() || $qaStatus === TicketQaStatusEnum::CHECKING) && $current?->qa_status?->isVerdict() && !$isRecheck) {
                        $fail(__('This ticket already has a QA verdict. Ask QA to check it again first.'));

                        return;
                    }

                    if (($qaStatus?->isVerdict() || $qaStatus === TicketQaStatusEnum::CHECKING) && $current?->isQaHeldByAnotherThan($user)) {
                        $fail(__('This check is with another checker.'));
                    }
                },
            ],
            'qa_note'       => ['nullable', 'string', 'max:10000', 'required_if:qa_status,'.TicketQaStatusEnum::FAILED->value.','.TicketQaStatusEnum::SKIPPED->value],
            'reopen'        => ['sometimes', 'boolean'],
            'qa_user_id'    => ['sometimes', 'nullable', Rule::in(GetTicketBadgeData::qaUsers($this->group->id)->pluck('id'))],
            'qa_user_ids'   => ['sometimes', 'array'],
            'qa_user_ids.*' => [Rule::in(GetTicketBadgeData::qaUsers($this->group->id)->pluck('id'))],
            'waiting_hours' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:720'],
            'deploy_commit' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9a-f]{7,40}$/i'],
            'images'        => ['sometimes', 'array', 'max:5'],
            'images.*'      => Ticket::ticketFileRules(),
            'tags.*'        => ['string', 'max:64'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        // Muting is the reporter's own call. It sits ahead of the blanket engineer/admin
        // early-return below, because nobody else - whatever they can otherwise do on the ticket
        // - gets to mute someone else's notifications.
        if (!$this->asAction && $request->has('reporter_muted') && array_diff(array_keys($request->except('_method')), ['reporter_muted']) === []) {
            $ticket = $request->route('ticket');

            return $ticket instanceof Ticket && $ticket->isReportedBy($request->user());
        }

        $user   = $request->user();
        $ticket = $request->route('ticket');

        if ($this->asAction || (Ticket::canBeAssignedBy($user) && (!$request->has('reporter_muted') || ($ticket instanceof Ticket && $ticket->isReportedBy($user))))) {
            return true;
        }

        if (!$ticket instanceof Ticket) {
            return false;
        }

        $fields = array_keys($request->except('_method'));

        if ($request->has('qa_status')) {
            if (array_diff($fields, ['qa_status', 'qa_note', 'qa_user_id', 'qa_user_ids', 'images', 'reopen']) !== []) {
                return false;
            }

            $qaStatus = TicketQaStatusEnum::tryFrom((string) $request->input('qa_status'));

            return $qaStatus?->isVerdict() || $qaStatus === TicketQaStatusEnum::CHECKING
                ? Ticket::canGiveQaVerdict($user)
                : $ticket->canRequestQaBy($user);
        }

        // The reporter's own cancel and reopen are additions to who could already do it: whoever
        // holds the ticket keeps every status of it, or the assignee is shown a Cancel button
        // that answers 403.
        if ($request->input('status') === TicketStatusEnum::CANCELLED->value && array_diff($fields, ['status', 'status_comment', 'images']) === []) {
            return $ticket->canBeCancelledByReporter($user) || $ticket->canBeUpdatedBy($user);
        }

        if ($request->input('status') === TicketStatusEnum::ANSWERED->value && $request->filled('status_comment') && array_diff($fields, ['status', 'status_comment', 'images']) === []) {
            return $ticket->canBeReopenedByReporter($user) || $ticket->canBeUpdatedBy($user);
        }

        if ($request->has('assignee_id')) {
            $canHandOver = $ticket->canChangeAssigneeBy($user) && ($request->filled('assignee_id') || Ticket::canBeAssignedBy($user));

            if (!$canHandOver) {
                return false;
            }

            if (array_diff($fields, ['assignee_id']) === []) {
                return true;
            }
        }

        if ($ticket->canBeUpdatedBy($user)) {
            return !$request->has('is_confidential') && (!$request->has('reporter_muted') || $ticket->isReportedBy($user));
        }

        if ($request->has('tags') && array_diff($fields, ['tags']) === [] && $ticket->hasCollaborator($user)) {
            return true;
        }

        return false;
    }

    /**
     * Whatever happened is written on the ticket, because "the customer was told" and "we tried"
     * are different things and the next person reading the ticket needs to know which it was.
     */
    public function getValidationMessages(): array
    {
        return Ticket::ticketFileValidationMessages();
    }

    public function getValidationAttributes(): array
    {
        return Ticket::ticketFileValidationAttributes($this->get('images', []));
    }

    private function tellTheCustomer(Ticket $ticket, string $note, ?User $actor): void
    {
        $outcome = EndTicketConversation::make()->handle($ticket, $note, $actor);

        $body = match (true) {
            $outcome['sent'] && $outcome['closed']  => __('This was sent to the customer and the conversation closed.'),
            $outcome['sent']                        => __('This was sent to the customer, but the conversation could not be closed.'),
            $outcome['reason'] === 'whatsapp_window_closed' => __('The conversation was closed. WhatsApp would not carry the message: the customer has not written in over a day.'),
            $outcome['reason'] === 'already_closed' => __('The conversation was already closed, so nothing was sent.'),
            $outcome['closed']                      => __('The conversation was closed, but the message could not be sent.'),
            default                                 => __('The customer could not be told and the conversation is still open.'),
        };

        $ticket->comments()->create([
            'author_type' => $actor ? 'User' : null,
            'author_id'   => $actor?->id,
            'body'        => $body,
            'is_internal' => true,
        ]);
    }

    private function reporterHearsAboutStatus(Ticket $ticket, ?User $actor): bool
    {
        if (in_array($ticket->status, [TicketStatusEnum::RESOLVED, TicketStatusEnum::WAITING, TicketStatusEnum::ANSWERED], true)) {
            return false;
        }

        return $actor !== null || $ticket->status !== TicketStatusEnum::OPEN;
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
