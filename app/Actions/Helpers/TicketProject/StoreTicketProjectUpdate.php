<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TicketProject;

use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketProjectHealthEnum;
use App\Models\Helpers\TicketProject;
use App\Models\Helpers\TicketProjectUpdate;
use App\Models\SysAdmin\User;
use App\Notifications\TicketProjectNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreTicketProjectUpdate extends OrgAction
{
    public function handle(TicketProject $project, User $author, array $modelData): TicketProjectUpdate
    {
        $update = $project->updates()->create([
            'author_id' => $author->id,
            'health'    => $modelData['health'] ?? null,
            'body'      => trim($modelData['body']),
        ]);

        $project->touch();

        $this->notifyTeam($project, $author, $update);

        return $update;
    }

    private function notifyTeam(TicketProject $project, User $author, TicketProjectUpdate $update): void
    {
        $team = $project->members()->where('users.status', true)->get()
            ->push($project->owner)
            ->filter(fn (?User $person) => $person && $person->status && $person->id !== $author->id)
            ->unique('id');

        if ($team->isEmpty()) {
            return;
        }

        $title = $update->health
            ? __(':project: :health', ['project' => $project->name, 'health' => TicketProjectHealthEnum::labels()[$update->health->value]])
            : __(':project: new update', ['project' => $project->name]);

        Notification::send($team, new TicketProjectNotification($project, $title, ($author->contact_name ?: $author->username).': '.$update->body));
    }

    public function authorize(ActionRequest $request): bool
    {
        $project = $request->route('ticketProject');

        return $project->group_id === $request->user()->group_id && $project->canBeEditedBy($request->user());
    }

    public function rules(): array
    {
        return [
            'body'   => ['required', 'string', 'max:20000'],
            'health' => ['sometimes', 'nullable', Rule::enum(TicketProjectHealthEnum::class)],
        ];
    }

    public function asController(TicketProject $ticketProject, ActionRequest $request): TicketProjectUpdate
    {
        $this->initialisationFromGroup($ticketProject->group, $request);

        return $this->handle($ticketProject, $request->user(), $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
