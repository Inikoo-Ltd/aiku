<?php

namespace App\Actions\Helpers\TicketProject;

use App\Actions\OrgAction;
use App\Models\Helpers\TicketProjectUpdate;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class DeleteTicketProjectUpdate extends OrgAction
{
    public function handle(TicketProjectUpdate $update): void
    {
        $update->delete();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('ticketProjectUpdate')->canBeDeletedBy($request->user());
    }

    public function asController(TicketProjectUpdate $ticketProjectUpdate, ActionRequest $request): void
    {
        $this->initialisationFromGroup($ticketProjectUpdate->project->group, $request);

        $this->handle($ticketProjectUpdate);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
