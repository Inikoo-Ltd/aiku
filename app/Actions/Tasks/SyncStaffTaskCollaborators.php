<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Tasks;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Http\Resources\Tasks\StaffTaskResource;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncStaffTaskCollaborators
{
    use AsAction;

    /**
     * @param array<int, int|string> $collaboratorIds
     */
    public function handle(StaffTask $task, array $collaboratorIds, User $actor): StaffTask
    {
        $previousIds = $task->collaborators()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        $wantedIds   = User::query()
            ->where('group_id', $task->group_id)
            ->where('status', true)
            ->whereIn('id', collect($collaboratorIds)->map(fn ($id) => (int) $id)->reject(fn (int $id) => $id === $task->assignee_id)->all())
            ->get()
            ->filter(fn (User $user) => StaffTask::canBeAssigned($user))
            ->pluck('id')
            ->all();

        $addedIds   = array_values(array_diff($wantedIds, $previousIds));
        $removedIds = array_values(array_diff($previousIds, $wantedIds));

        if ($addedIds === [] && $removedIds === []) {
            return $task;
        }

        $task->collaborators()->detach($removedIds);
        $task->collaborators()->attach(collect($addedIds)->mapWithKeys(fn (int $id) => [$id => ['added_by_id' => $actor->id]])->all());

        $conversation = $task->conversation;
        $conversation->participants()->syncWithoutDetaching(array_merge($addedIds, [$actor->id]));
        $conversation->participants()->detach(array_diff($removedIds, [$task->requester_id, $task->assignee_id, $actor->id]));

        $names = fn (array $ids) => User::whereIn('id', $ids)->get()->map(fn (User $user) => $user->chatName())->implode(', ');
        $lines = array_filter([
            $addedIds ? __('Working on this too: :names', ['names' => $names($addedIds)]) : null,
            $removedIds ? __('No longer working on this: :names', ['names' => $names($removedIds)]) : null,
        ]);
        SendStaffMessage::run($conversation, $actor, ['body' => implode("\n", $lines)]);

        return $task;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffTask')->isVisibleTo($request->user());
    }

    public function rules(): array
    {
        return [
            'collaborator_ids'   => ['present', 'array', 'max:20'],
            'collaborator_ids.*' => ['integer', Rule::exists('users', 'id')->where('group_id', request()->user()->group_id)],
        ];
    }

    public function asController(StaffTask $staffTask, ActionRequest $request): StaffTaskResource
    {
        $task = $this->handle($staffTask, $request->validated('collaborator_ids'), $request->user());

        return new StaffTaskResource($task->load(['requester', 'assignee', 'collaborators.image', 'conversation.participants', 'model']));
    }
}
