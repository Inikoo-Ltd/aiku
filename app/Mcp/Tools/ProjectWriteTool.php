<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Helpers\TicketProject\AssignWorkToProject;
use App\Actions\Helpers\TicketProject\AttachWorkToProject;
use App\Actions\Helpers\TicketProject\StoreTicketProject;
use App\Actions\Helpers\TicketProject\StoreTicketProjectUpdate;
use App\Actions\Helpers\TicketProject\UpdateTicketProject;
use App\Enums\Helpers\Ticket\TicketProjectHealthEnum;
use App\Enums\Helpers\Ticket\TicketProjectStatusEnum;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketProject;
use App\Models\Helpers\TicketProjectMilestone;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Changes projects (see projects-tool), acting as the authenticated user with the same rights as the project page: the owner, the team and ticket leads can change a project; anyone can create one. Pick one action. create: a new project from name, start_date and optional goal, target_date, owner and team (aiku usernames). update: change name, goal, status, start_date, target_date, owner, or add_team/remove_team usernames. add_work: put tickets and staff tasks into the project by reference (e.g. "HELP-12, TASK-4"), optionally under a milestone. move_work: move items already in the project to another milestone (milestone "none" takes them off any milestone), or take them out of the project with remove=true. milestone: create a milestone (new name), or change an existing one (by name or id) with rename, start_date, due_date, done true/false, delete=true, or position (1 = first). post_update: post a progress update with optional health (on_track, at_risk, off_track); the owner and team are notified by bell and email. Changes show in the item\'s history. Confirm with the user before deleting milestones or taking work out.')]
class ProjectWriteTool extends Tool
{
    public function handle(Request $request): Response
    {
        $request->validate([
            'action' => ['required', 'in:create,update,add_work,move_work,milestone,post_update'],
        ]);

        $user   = $request->user();
        $action = $request->string('action')->toString();

        if ($action === 'create') {
            return $this->create($request, $user);
        }

        $project = ProjectsTool::findProject($user, (string) $request->get('project', ''));
        if (!$project) {
            return Response::error('Give an existing project slug. Projects: '.TicketProject::where('group_id', $user->group_id)->orderBy('name')->pluck('slug')->implode(', ').'.');
        }

        $isMovingOwnWork = $action === 'move_work';
        if (!$isMovingOwnWork && !$project->canBeEditedBy($user)) {
            return Response::error('Only the owner, the team and ticket leads can change '.$project->name.'.');
        }

        try {
            return match ($action) {
                'update'      => $this->update($request, $user, $project),
                'add_work'    => $this->addWork($request, $user, $project),
                'move_work'   => $this->moveWork($request, $user, $project),
                'milestone'   => $this->milestone($request, $project),
                'post_update' => $this->postUpdate($request, $user, $project),
            };
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()));
        }
    }

    private function create(Request $request, User $user): Response
    {
        try {
            $project = StoreTicketProject::make()->action($user->group, array_filter([
                'name'        => $request->get('name'),
                'description' => $request->get('goal'),
                'start_date'  => $request->get('start_date', now()->toDateString()),
                'target_date' => $request->get('target_date'),
                'owner_id'    => $request->filled('owner') ? $this->userId($user, $request->string('owner')->toString()) : $user->id,
                'member_ids'  => $this->userIds($user, $request->get('team')),
            ], fn ($value) => $value !== null && $value !== ''));
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()));
        }

        return Response::json(['created' => $project->slug, 'url' => route('grp.tickets.projects.show', $project->slug)]);
    }

    private function update(Request $request, User $user, TicketProject $project): Response
    {
        $modelData = array_filter([
            'name'        => $request->get('name'),
            'description' => $request->get('goal'),
            'status'      => $request->get('status'),
            'start_date'  => $request->get('start_date'),
            'target_date' => $request->get('target_date'),
            'owner_id'    => $request->filled('owner') ? $this->userId($user, $request->string('owner')->toString()) : null,
        ], fn ($value) => $value !== null && $value !== '');

        if ($request->filled('add_team') || $request->filled('remove_team')) {
            $modelData['member_ids'] = $project->members()->pluck('users.id')
                ->merge($this->userIds($user, $request->get('add_team')))
                ->diff($this->userIds($user, $request->get('remove_team')))
                ->unique()->values()->all();
        }

        if ($modelData === []) {
            return Response::error('Nothing to change: give name, goal, status, start_date, target_date, owner, add_team or remove_team.');
        }

        $project = UpdateTicketProject::make()->action($project, $modelData);

        return Response::json(['updated' => $project->slug, 'changed' => array_keys($modelData)]);
    }

    private function addWork(Request $request, User $user, TicketProject $project): Response
    {
        $milestone = $this->milestoneFrom($project, $request->get('milestone'));
        if ($milestone === false) {
            return $this->noSuchMilestone($project, $request->get('milestone'));
        }

        $work = AttachWorkToProject::make()->handle($project, $user, (string) $request->get('references', ''), $milestone?->id);

        return Response::json(['added' => $work->pluck('reference')->all(), 'milestone' => $milestone?->name]);
    }

    private function moveWork(Request $request, User $user, TicketProject $project): Response
    {
        $references = collect(preg_split('/[\s,;]+/', strtoupper((string) $request->get('references', ''))))->filter()->unique();
        $work       = $this->workInProject($project, $user, $references);

        $missing = $references->diff($work->pluck('reference'));
        if ($missing->isNotEmpty()) {
            return Response::error('Not in '.$project->name.' (or not visible to you): '.$missing->implode(', ').'. Use add_work for items outside the project.');
        }

        $isRemoving = $request->boolean('remove');
        $milestone  = $isRemoving ? null : $this->milestoneFrom($project, $request->get('milestone'));
        if ($milestone === false) {
            return $this->noSuchMilestone($project, $request->get('milestone'));
        }

        $refused = $work->reject(fn (Ticket|StaffTask $item) => AssignWorkToProject::canAssign($item, $user, $isRemoving ? null : $project->id));
        if ($refused->isNotEmpty()) {
            return Response::error('You cannot move '.$refused->pluck('reference')->implode(', ').': only people working on them, or who can change the project, can.');
        }

        $work->each(fn (Ticket|StaffTask $item) => AssignWorkToProject::make()->action($item, $isRemoving
            ? ['ticket_project_id' => null]
            : ['ticket_project_milestone_id' => $milestone?->id]));

        return Response::json([
            'moved'     => $work->pluck('reference')->all(),
            'to'        => $isRemoving ? 'out of the project' : ($milestone?->name ?? 'no milestone'),
        ]);
    }

    private function milestone(Request $request, TicketProject $project): Response
    {
        $milestones = $project->milestones()->get()->values();
        $target     = $this->milestoneFrom($project, $request->get('milestone'));

        $list = $milestones->map(fn (TicketProjectMilestone $milestone) => [
            'id'          => $milestone->id,
            'name'        => $milestone->name,
            'description' => $milestone->description,
            'start_date'  => $milestone->start_date?->toDateString(),
            'due_date'    => $milestone->due_date?->toDateString(),
            'done'        => $milestone->done_at !== null,
        ]);

        if (!$target) {
            if (!$request->filled('milestone') || $request->boolean('delete')) {
                return $this->noSuchMilestone($project, $request->get('milestone'));
            }
            $entry = ['name' => (string) $request->get('milestone'), 'start_date' => $request->get('start_date'), 'due_date' => $request->get('due_date'), 'done' => $request->boolean('done')];
            $list  = $list->push($entry);
            $index = $list->count() - 1;
            $verb  = 'created';
        } else {
            $index = $list->search(fn (array $entry) => $entry['id'] === $target->id);
            if ($request->boolean('delete')) {
                $list = $list->forget($index)->values();
                UpdateTicketProject::make()->action($project, ['milestones' => $list->all()]);

                return Response::json(['deleted' => $target->name, 'note' => 'Its tickets and tasks stay in the project without a milestone.']);
            }
            $list = $list->put($index, array_merge($list[$index], array_filter([
                'name'       => $request->get('rename'),
                'start_date' => $request->get('start_date'),
                'due_date'   => $request->get('due_date'),
            ], fn ($value) => $value !== null && $value !== ''), $request->has('done') ? ['done' => $request->boolean('done')] : []));
            $verb = 'updated';
        }

        if ($request->filled('position')) {
            $entry    = $list->pull($index);
            $position = max(0, min($list->count(), (int) $request->get('position') - 1));
            $list     = $list->values()->take($position)->push($entry)->concat($list->values()->slice($position))->values();
        }

        UpdateTicketProject::make()->action($project, ['milestones' => $list->values()->all()]);

        return Response::json([$verb => $request->get('rename') ?: ($target?->name ?? $request->get('milestone')), 'milestones' => $project->milestones()->pluck('name')->all()]);
    }

    private function postUpdate(Request $request, User $user, TicketProject $project): Response
    {
        if (!$request->filled('body')) {
            return Response::error('Give the update text in body.');
        }
        if ($request->filled('health') && !TicketProjectHealthEnum::tryFrom((string) $request->get('health'))) {
            return Response::error('health is one of: '.implode(', ', array_column(TicketProjectHealthEnum::cases(), 'value')).'.');
        }

        $update = StoreTicketProjectUpdate::make()->handle($project, $user, ['body' => (string) $request->get('body'), 'health' => $request->get('health') ?: null]);

        return Response::json(['posted' => $update->id, 'health' => $update->health?->value, 'notified' => 'owner and team']);
    }

    /**
     * @return TicketProjectMilestone|null|false null when none was asked for, false when it does not exist
     */
    private function milestoneFrom(TicketProject $project, mixed $identifier): TicketProjectMilestone|null|false
    {
        $identifier = trim((string) $identifier);
        if ($identifier === '' || strtolower($identifier) === 'none') {
            return null;
        }

        return $project->milestones()
            ->where(fn ($query) => ctype_digit($identifier) ? $query->whereKey((int) $identifier) : $query->whereRaw('lower(name) = ?', [mb_strtolower($identifier)]))
            ->first() ?? false;
    }

    private function noSuchMilestone(TicketProject $project, mixed $identifier): Response
    {
        return Response::error('No milestone "'.$identifier.'" in '.$project->name.'. Milestones: '.($project->milestones()->pluck('name')->implode(', ') ?: 'none yet').'.');
    }

    /**
     * @return Collection<int, Ticket|StaffTask>
     */
    private function workInProject(TicketProject $project, User $user, Collection $references): Collection
    {
        return $project->tickets()->whereIn('reference', $references)->visibleTo($user)->get()
            ->concat($project->staffTasks()->whereIn('reference', $references)->visibleTo($user)->get());
    }

    private function userId(User $user, string $username): int
    {
        $id = $user->group->users()->whereRaw('lower(username) = ?', [strtolower(trim($username))])->value('id');
        if (!$id) {
            throw ValidationException::withMessages(['owner' => 'No aiku user with username "'.$username.'".']);
        }

        return $id;
    }

    /**
     * @return array<int, int>
     */
    private function userIds(User $user, mixed $usernames): array
    {
        $names = collect(is_array($usernames) ? $usernames : preg_split('/[\s,;]+/', (string) $usernames))->map(fn ($name) => strtolower(trim((string) $name)))->filter()->unique();
        if ($names->isEmpty()) {
            return [];
        }

        $ids     = $user->group->users()->whereIn(DB::raw('lower(username)'), $names)->get(['id', 'username'])->mapWithKeys(fn (User $person) => [strtolower($person->username) => $person->id]);
        $missing = $names->diff($ids->keys());
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['team' => 'No aiku user with username: '.$missing->implode(', ').'.']);
        }

        return $ids->values()->all();
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action'      => $schema->string()->description('create, update, add_work, move_work, milestone or post_update')->required(),
            'project'     => $schema->string()->description('Project slug or exact name (all actions except create)'),
            'name'        => $schema->string()->description('create/update: project name'),
            'goal'        => $schema->string()->description('create/update: what the project must achieve'),
            'status'      => $schema->string()->description('update: '.implode(', ', array_column(TicketProjectStatusEnum::cases(), 'value'))),
            'start_date'  => $schema->string()->description('create/update/milestone: YYYY-MM-DD'),
            'target_date' => $schema->string()->description('create/update: YYYY-MM-DD'),
            'due_date'    => $schema->string()->description('milestone: YYYY-MM-DD'),
            'owner'       => $schema->string()->description('create/update: aiku username of the owner (create defaults to the user)'),
            'team'        => $schema->string()->description('create: aiku usernames, comma separated'),
            'add_team'    => $schema->string()->description('update: aiku usernames to add to the team, comma separated'),
            'remove_team' => $schema->string()->description('update: aiku usernames to remove from the team, comma separated'),
            'references'  => $schema->string()->description('add_work/move_work: ticket and task references, e.g. "HELP-12, TASK-4"'),
            'milestone'   => $schema->string()->description('add_work/move_work: milestone name or id ("none" for no milestone). milestone action: the milestone to change, or the name of a new one'),
            'remove'      => $schema->boolean()->description('move_work: true takes the items out of the project'),
            'rename'      => $schema->string()->description('milestone: new name'),
            'done'        => $schema->boolean()->description('milestone: mark done (true) or not done (false)'),
            'delete'      => $schema->boolean()->description('milestone: delete it; its work stays in the project'),
            'position'    => $schema->integer()->description('milestone: place in the list, 1 = first'),
            'body'        => $schema->string()->description('post_update: the update text'),
            'health'      => $schema->string()->description('post_update: on_track, at_risk or off_track'),
        ];
    }
}
