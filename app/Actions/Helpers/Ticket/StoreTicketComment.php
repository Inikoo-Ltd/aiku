<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketCommentTypeEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketComment;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\ValidationException;

class StoreTicketComment extends OrgAction
{
    /**
     * @param bool $isStatusNote the comment was written with a status change, which already notifies the reporter, so it only notifies the people it @mentions
     */
    public function handle(Ticket $ticket, User|WebUser $author, array $modelData, bool $mirrorToSlack = true, bool $notifyUsers = true, bool $isStatusNote = false): TicketComment
    {
        if (Arr::get($modelData, 'is_internal') && !($author instanceof User && $ticket->canWriteEngineeringNotesBy($author))) {
            throw ValidationException::withMessages(['is_internal' => __('Only engineers and collaborators can write engineering notes.')]);
        }

        $isPostMortem = Arr::get($modelData, 'type') === TicketCommentTypeEnum::POST_MORTEM->value;

        if ($isPostMortem && !($author instanceof User && $ticket->canWriteEngineeringNotesBy($author))) {
            throw ValidationException::withMessages(['type' => __('Only engineers and collaborators can write incident post-mortems.')]);
        }

        $comment = $ticket->comments()->create([
            'author_type' => $author instanceof User ? 'User' : 'WebUser',
            'author_id'   => $author->id,
            'body'        => (string) Arr::get($modelData, 'body', ''),
            'is_internal' => (bool) Arr::get($modelData, 'is_internal', false),
            'type'        => $isPostMortem ? TicketCommentTypeEnum::POST_MORTEM : TicketCommentTypeEnum::COMMENT,
        ]);

        $comment->attachTicketImages(Arr::get($modelData, 'images', []));
        $ticket->touch();

        if ($mirrorToSlack && !$comment->is_internal) {
            PostTicketSlackThreadReply::run($ticket, ($author->contact_name ?? $author->email).': '.Str::limit($comment->body, 2000));
        }

        if ($this->replyReopens($ticket, $author)) {
            $status = $ticket->status === TicketStatusEnum::WAITING ? TicketStatusEnum::ANSWERED : TicketStatusEnum::OPEN;
            UpdateTicket::make()->action($ticket, ['status' => $status->value]);
        }

        if ($author instanceof User && $notifyUsers && !$comment->is_internal && $isStatusNote) {
            NotifyTicketUsers::make()->mentioned($ticket, $author, $comment->body);
        }

        if ($author instanceof User && $notifyUsers && !$comment->is_internal && !$isStatusNote) {
            NotifyTicketUsers::make()->commented($ticket, $author, $comment->body);
        }

        if ($author instanceof User && $notifyUsers && $comment->is_internal) {
            NotifyTicketUsers::make()->mentionedInEngineeringNote($ticket, $author, $comment->body);
        }

        NotifyTicketUsers::make()->pushBadges($ticket, $author instanceof User ? $author : null);

        return $comment;
    }

    private function replyReopens(Ticket $ticket, User|WebUser $author): bool
    {
        if (!in_array($ticket->status, [TicketStatusEnum::WAITING, TicketStatusEnum::CANCELLED], true)) {
            return false;
        }

        return $author instanceof WebUser || $ticket->isReportedBy($author) || !Ticket::canBeManagedBy($author);
    }

    public function rules(): array
    {
        return [
            'body'        => ['required_without:images', 'nullable', 'string', 'max:10000'],
            'is_internal' => ['sometimes', 'boolean'],
            'type'        => ['sometimes', 'in:'.TicketCommentTypeEnum::COMMENT->value.','.TicketCommentTypeEnum::POST_MORTEM->value],
            'images'   => ['sometimes', 'array', 'max:5'],
            'images.*' => Ticket::ticketFileRules(),
        ];
    }

    public function getValidationMessages(): array
    {
        return Ticket::ticketFileValidationMessages();
    }

    public function getValidationAttributes(): array
    {
        return Ticket::ticketFileValidationAttributes($this->get('images', []));
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $user = $request->user();

        return $user instanceof User && $request->route('ticket')->isVisibleTo($user);
    }

    public function action(Ticket $ticket, User|WebUser $author, array $modelData, bool $mirrorToSlack = true, bool $notifyUsers = true, bool $isStatusNote = false): TicketComment
    {
        $this->asAction = true;
        $this->initialisationFromGroup($ticket->group, $modelData);

        return $this->handle($ticket, $author, $this->validatedData, $mirrorToSlack, $notifyUsers, $isStatusNote);
    }

    public function asController(Ticket $ticket, ActionRequest $request): TicketComment
    {
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($ticket, $request->user(), $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
