<?php

/*
 * Author Louis Perez
 * Created on 14-09-2026-15h-01m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket\UI;

use App\Actions\OrgAction;
use App\Models\Helpers\Media;
use App\Models\Helpers\Ticket;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShowTicketAttachment extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    public function handle(Media $media): StreamedResponse
    {
        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $media->name, ['Content-Type' => $media->mime_type], 'inline');
    }

    public function asController(Ticket $ticket, Media $media, ActionRequest $request): StreamedResponse
    {
        abort_unless($ticket->isVisibleTo($request->user()), 403);
        abort_unless($ticket->hasAttachmentVisibleTo($media, $request->user()), 404);
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($media);
    }
}
