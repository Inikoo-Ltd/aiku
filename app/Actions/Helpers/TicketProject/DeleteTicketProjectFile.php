<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 19:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TicketProject;

use App\Actions\OrgAction;
use App\Models\Helpers\Media;
use App\Models\Helpers\TicketProject;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class DeleteTicketProjectFile extends OrgAction
{
    public function handle(TicketProject $project, Media $media): void
    {
        $media->delete();
        $project->touch();
    }

    public function authorize(ActionRequest $request): bool
    {
        $project = $request->route('ticketProject');

        return $project->group_id === $request->user()->group_id && $project->canBeEditedBy($request->user());
    }

    public function asController(TicketProject $ticketProject, Media $media, ActionRequest $request): void
    {
        abort_unless($ticketProject->hasFile($media), 404);
        $this->initialisationFromGroup($ticketProject->group, $request);

        $this->handle($ticketProject, $media);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
