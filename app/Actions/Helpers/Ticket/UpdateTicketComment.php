<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketComment;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateTicketComment extends OrgAction
{
    /**
     * Edited down to nothing — no text, no files left — is the same as deleting it: there is
     * nothing left to show, so saving removes the comment rather than leaving an empty husk
     * or refusing to save at all.
     *
     * @param array{body: string, remove_media?: array<int, string>, images?: array<int, \Illuminate\Http\UploadedFile>} $modelData
     */
    public function handle(TicketComment $ticketComment, array $modelData): TicketComment
    {
        $ticketComment->media()->whereIn('ulid', Arr::get($modelData, 'remove_media', []))->get()->each->delete();

        $remainingSlots = max(0, 5 - $ticketComment->media()->count());
        $ticketComment->attachTicketImages(array_slice(Arr::get($modelData, 'images', []), 0, $remainingSlots));

        $body = trim((string) Arr::get($modelData, 'body', ''));

        if ($body === '' && !$ticketComment->media()->exists()) {
            $ticketComment->delete();
        } else {
            $ticketComment->update(['body' => $body]);
        }

        $actor = request()->user();
        NotifyTicketUsers::make()->pushBadges($ticketComment->ticket, $actor instanceof User ? $actor : null);

        return $ticketComment;
    }

    public function rules(): array
    {
        return [
            'body'           => ['present', 'nullable', 'string', 'max:10000'],
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

        return $request->route('ticketComment')->isAuthoredBy($request->user());
    }

    public function action(TicketComment $ticketComment, array $modelData): TicketComment
    {
        $this->asAction = true;
        $this->initialisationFromGroup($ticketComment->ticket->group, $modelData);

        return $this->handle($ticketComment, $this->validatedData);
    }

    public function asController(TicketComment $ticketComment, ActionRequest $request): TicketComment
    {
        $this->initialisationFromGroup($ticketComment->ticket->group, $request);

        return $this->handle($ticketComment, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
