<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 19:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TicketProject;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\OrgAction;
use App\Models\Helpers\Media;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketProject;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Lorisleiva\Actions\ActionRequest;

class StoreTicketProjectFile extends OrgAction
{
    public function handle(TicketProject $project, User $uploader, UploadedFile $file): Media
    {
        $media = StoreMediaFromFile::run($project, [
            'path'         => $file->getPathname(),
            'originalName' => $file->getClientOriginalName(),
            'extension'    => $file->getClientOriginalExtension() ?: null,
            'checksum'     => md5_file($file->getPathname()),
        ], TicketProject::FILES_COLLECTION, 'file');

        $media->setCustomProperty('uploaded_by', $uploader->id)->save();
        $project->touch();

        return $media;
    }

    public function authorize(ActionRequest $request): bool
    {
        $project = $request->route('ticketProject');

        return $project->group_id === $request->user()->group_id && $project->canBeEditedBy($request->user());
    }

    public function rules(): array
    {
        return [
            'files'   => ['required', 'array', 'max:5'],
            'files.*' => Ticket::ticketFileRules(),
        ];
    }

    public function asController(TicketProject $ticketProject, ActionRequest $request): void
    {
        $this->initialisationFromGroup($ticketProject->group, $request);

        foreach ($this->validatedData['files'] as $file) {
            $this->handle($ticketProject, $request->user(), $file);
        }
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
