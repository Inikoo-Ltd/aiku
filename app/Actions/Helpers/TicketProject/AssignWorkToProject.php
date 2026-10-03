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
use App\Models\Helpers\TicketProjectMilestone;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class AssignWorkToProject extends OrgAction
{
    private Ticket|StaffTask|null $work = null;

    public function handle(Ticket|StaffTask $work, array $modelData): Ticket|StaffTask
    {
        $projectId = Arr::exists($modelData, 'ticket_project_id') ? $modelData['ticket_project_id'] : $work->ticket_project_id;
        $projectId = $projectId ? (int) $projectId : null;

        $milestoneId = Arr::exists($modelData, 'ticket_project_milestone_id')
            ? $modelData['ticket_project_milestone_id']
            : ($projectId === $work->ticket_project_id ? $work->ticket_project_milestone_id : null);

        $work->update([
            'ticket_project_id'           => $projectId,
            'ticket_project_milestone_id' => $projectId ? $milestoneId : null,
        ]);

        if ($work instanceof Ticket) {
            $work->touch();
        }

        return $work;
    }

    public static function canAssign(Ticket|StaffTask $work, User $user, ?int $targetProjectId): bool
    {
        $isWorker = $work instanceof Ticket ? $work->canContributeBy($user) : $work->isWorkedOnBy($user) || $work->requester_id === $user->id;
        if ($isWorker) {
            return true;
        }

        $projects = TicketProject::whereIn('id', array_filter([$work->ticket_project_id, $targetProjectId]))->get();

        return $projects->isNotEmpty() && $projects->every(fn (TicketProject $project) => $project->canBeEditedBy($user));
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $this->work ??= $request->route('ticket') ?? $request->route('staffTask');
        if ($this->work->group_id !== $request->user()->group_id) {
            return false;
        }

        $target     = $request->input('ticket_project_id', $this->work->ticket_project_id);

        return $this->work->isVisibleTo($request->user()) && self::canAssign($this->work, $request->user(), $target ? (int) $target : null);
    }

    public function rules(): array
    {
        return [
            'ticket_project_id'           => ['sometimes', 'nullable', Rule::exists('ticket_projects', 'id')->where('group_id', $this->group->id)->whereNull('deleted_at')],
            'ticket_project_milestone_id' => [
                'sometimes',
                'nullable',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $projectId = $this->has('ticket_project_id') ? $this->get('ticket_project_id') : $this->work?->ticket_project_id;
                    if ($value && !TicketProjectMilestone::whereKey($value)->where('ticket_project_id', $projectId)->exists()) {
                        $fail(__('That milestone is not in this project.'));
                    }
                },
            ],
        ];
    }

    public function action(Ticket|StaffTask $work, array $modelData): Ticket|StaffTask
    {
        $this->asAction = true;
        $this->work     = $work;
        $this->initialisationFromGroup($work->group, $modelData);

        return $this->handle($work, $this->validatedData);
    }

    public function inTicket(Ticket $ticket, ActionRequest $request): Ticket
    {
        $this->work = $ticket;
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($ticket, $this->validatedData);
    }

    public function inStaffTask(StaffTask $staffTask, ActionRequest $request): StaffTask
    {
        $this->work = $staffTask;
        $this->initialisationFromGroup($staffTask->group, $request);

        return $this->handle($staffTask, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
