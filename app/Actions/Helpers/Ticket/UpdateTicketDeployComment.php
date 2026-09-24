<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketCommentTypeEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketComment;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateTicketDeployComment extends OrgAction
{
    /**
     * Rewrites the comment held back until the deployment lands. Authorship moves to whoever wrote
     * the text that will actually be posted, so the reporter sees the right name on it. With no
     * text and no files left there is nothing to post, so the held comment goes.
     *
     * @param array{body: string, images?: array<int, \Illuminate\Http\UploadedFile>, remove_media?: array<int, string>} $modelData
     */
    public function handle(Ticket $ticket, array $modelData): Ticket
    {
        if ($ticket->status !== TicketStatusEnum::PENDING_DEPLOY) {
            abort(403, 'This ticket is not waiting for a deployment');
        }

        $body    = trim((string) Arr::get($modelData, 'body', ''));
        $images  = Arr::get($modelData, 'images', []);
        $author  = request()->user();
        $comment = $ticket->deployComment()->first();

        if (!$comment) {
            if ($body === '' && $images === []) {
                return $ticket;
            }

            $comment = TicketComment::create([
                'ticket_id'   => $ticket->id,
                'author_type' => 'User',
                'author_id'   => $author instanceof User ? $author->id : null,
                'body'        => $body,
                'type'        => TicketCommentTypeEnum::WAITING_FOR_DEPLOYMENT,
            ]);
        } else {
            $comment->update([
                'body'      => $body,
                'author_id' => $author instanceof User ? $author->id : $comment->author_id,
            ]);
        }

        $comment->media()->whereIn('ulid', Arr::get($modelData, 'remove_media', []))->get()->each->delete();
        $comment->attachTicketImages($images);

        if ($body === '' && !$comment->media()->exists()) {
            $comment->delete();
        }

        return $ticket;
    }

    public function rules(): array
    {
        return [
            'body'           => ['present', 'nullable', 'string', 'max:10000'],
            'images'         => ['sometimes', 'array', 'max:5'],
            'images.*'       => Ticket::ticketFileRules(),
            'remove_media'   => ['sometimes', 'array'],
            'remove_media.*' => ['string'],
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

        return $request->route('ticket')->canContributeBy($request->user());
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
