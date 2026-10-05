<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Mon, 05 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateTicketContent extends OrgAction
{
    /**
     * Only the ticket's own files can be removed here; files on comments stay with their comment.
     * Subject and description changes are audited like any field, and the files that came or went
     * are written to the history by name.
     *
     * @param array{subject?: string, description?: string|null, remove_media?: array<int, string>, images?: array<int, \Illuminate\Http\UploadedFile>} $modelData
     */
    public function handle(Ticket $ticket, array $modelData): Ticket
    {
        $files = $ticket->replaceOwnTicketFiles(Arr::get($modelData, 'remove_media', []), Arr::get($modelData, 'images', []));

        $ticket->update(Arr::only($modelData, ['subject', 'description']));
        $ticket->recordTicketFilesInHistory($files['removed'], $files['added']);

        $actor = request()->user();
        NotifyTicketUsers::make()->pushBadges($ticket, $actor instanceof User ? $actor : null);

        return $ticket;
    }

    public function rules(): array
    {
        return [
            'subject'        => ['sometimes', 'required', 'string', 'max:255'],
            'description'    => ['sometimes', 'nullable', 'string', 'max:20000'],
            'remove_media'   => ['sometimes', 'array'],
            'remove_media.*' => ['string'],
            'images'         => ['sometimes', 'array', 'max:5'],
            'images.*'       => Ticket::ticketFileRules(),
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

        return $request->route('ticket')->canEditContentBy($request->user());
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
