<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TicketProject;

use App\Actions\OrgAction;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketProject;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class AttachWorkToProject extends OrgAction
{
    /**
     * Tickets and staff tasks pasted by reference, any mix of them in one go.
     *
     * @return Collection<int, Ticket|StaffTask>
     */
    public function handle(TicketProject $project, User $user, string $references, ?int $milestoneId = null): Collection
    {
        $wanted = collect(preg_split('/[\s,;]+/', strtoupper($references)))->filter()->unique();

        $work = Ticket::where('group_id', $project->group_id)->whereIn('reference', $wanted)->visibleTo($user)->get()
            ->concat(StaffTask::where('group_id', $project->group_id)->whereIn('reference', $wanted)->visibleTo($user)->get());

        $missing = $wanted->diff($work->pluck('reference'));
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['references' => __('Not found: :references', ['references' => $missing->implode(', ')])]);
        }

        $notYours = $work->reject(fn (Ticket|StaffTask $item) => AssignWorkToProject::canAssign($item, $user, $project->id));
        if ($notYours->isNotEmpty()) {
            throw ValidationException::withMessages(['references' => __('These belong to a project you cannot change: :references', ['references' => $notYours->pluck('reference')->implode(', ')])]);
        }

        $work->each(fn (Ticket|StaffTask $item) => AssignWorkToProject::make()->action($item, [
            'ticket_project_id'           => $project->id,
            'ticket_project_milestone_id' => $milestoneId ?? ($item->ticket_project_id === $project->id ? $item->ticket_project_milestone_id : null),
        ]));

        return $work;
    }

    public function authorize(ActionRequest $request): bool
    {
        $project = $request->route('ticketProject');

        return $project->group_id === $request->user()->group_id && $project->canBeEditedBy($request->user());
    }

    public function rules(): array
    {
        return [
            'references'                  => ['required', 'string', 'max:5000'],
            'ticket_project_milestone_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function asController(TicketProject $ticketProject, ActionRequest $request): Collection
    {
        $this->initialisationFromGroup($ticketProject->group, $request);

        $milestoneId = $this->validatedData['ticket_project_milestone_id'] ?? null;
        abort_if($milestoneId && !$ticketProject->milestones()->whereKey($milestoneId)->exists(), 422);

        return $this->handle($ticketProject, $request->user(), $this->validatedData['references'], $milestoneId);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
