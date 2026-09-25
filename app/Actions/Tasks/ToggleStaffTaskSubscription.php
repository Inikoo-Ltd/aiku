<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Tasks;

use App\Http\Resources\Tasks\StaffTaskResource;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ToggleStaffTaskSubscription
{
    use AsAction;

    public function handle(StaffTask $task, User $user): StaffTask
    {
        $conversation = $task->conversation;

        if (!$conversation->hasParticipant($user)) {
            $conversation->participants()->syncWithoutDetaching([$user->id]);
        } elseif (!in_array($user->id, array_merge([$task->requester_id, $task->assignee_id], $task->collaborators()->pluck('users.id')->all()), true)) {
            $conversation->participants()->detach($user->id);
        }

        return $task;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffTask')->conversation?->canBeAccessedBy($request->user()) ?? false;
    }

    public function asController(StaffTask $staffTask, ActionRequest $request): StaffTaskResource
    {
        $task = $this->handle($staffTask, $request->user());

        return new StaffTaskResource($task->load(['requester', 'assignee', 'collaborators.image', 'conversation.participants', 'model']));
    }
}
