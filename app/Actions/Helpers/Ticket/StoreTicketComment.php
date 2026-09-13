<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketComment;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class StoreTicketComment extends OrgAction
{
    public function handle(Ticket $ticket, User|WebUser $author, array $modelData, bool $mirrorToSlack = true, bool $notifyUsers = true): TicketComment
    {

        $comment = $ticket->comments()->create([
            'author_type' => $author instanceof User ? 'User' : 'WebUser',
            'author_id'   => $author->id,
            'body'        => (string) Arr::get($modelData, 'body', ''),
        ]);

        $comment->attachTicketImages(Arr::get($modelData, 'images', []));
        $ticket->touch();

        NotifyTicketUsers::make()->pushBadges($ticket, $author instanceof User ? $author : null);

        if ($mirrorToSlack) {
            PostTicketSlackThreadReply::run($ticket, ($author->contact_name ?? $author->email).': '.Str::limit($comment->body, 2000));
        }

        if ($this->replyReopens($ticket, $author)) {
            UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::OPEN->value]);
        }

        if ($author instanceof User && $notifyUsers) {
            NotifyTicketUsers::make()->commented($ticket, $author, $comment->body);
        }

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
            'body'     => ['required_without:images', 'nullable', 'string', 'max:10000'],
            'images'   => ['sometimes', 'array', 'max:5'],
            'images.*' => ['image', 'max:10240'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->asAction || $request->user() !== null;
    }

    public function action(Ticket $ticket, User|WebUser $author, array $modelData, bool $mirrorToSlack = true, bool $notifyUsers = true): TicketComment
    {
        $this->asAction = true;
        $this->initialisationFromGroup($ticket->group, $modelData);

        return $this->handle($ticket, $author, $this->validatedData, $mirrorToSlack);
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
