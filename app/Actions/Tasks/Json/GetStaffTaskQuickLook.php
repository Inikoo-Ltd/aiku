<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Tasks\Json;

use App\Actions\Tasks\UI\ShowStaffTask;
use App\Http\Resources\Chat\StaffMessageResource;
use App\Http\Resources\Tasks\StaffTaskResource;
use App\Models\Tasks\StaffTask;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetStaffTaskQuickLook
{
    use AsAction;

    public const int RECENT_MESSAGES = 5;

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffTask')->isVisibleTo($request->user());
    }

    public function asController(StaffTask $staffTask, ActionRequest $request): array
    {
        $show         = ShowStaffTask::make();
        $task         = $show->handle($staffTask);
        $conversation = $task->conversation;
        $canReadChat  = $conversation?->canBeAccessedBy($request->user()) ?? false;

        $show->markNotificationsRead($task, $request->user());

        return [
            'task'          => StaffTaskResource::make($task)->resolve(),
            'linked_url'    => $show->linkedRecordUrl($task),
            'can_edit'      => $task->isWorkedOnBy($request->user()),
            'due_access'    => $task->dueAccessFor($request->user()),
            'can_remove_collaborators' => $task->canRemoveCollaboratorsBy($request->user()),
            'can_reassign' => $task->canReassignBy($request->user()),
            'can_ask_for_help' => $task->canAskForHelpBy($request->user()),
            'options'       => StaffTask::editOptions(),
            ...$show->projectControls($task, $request->user()),
            'messages'      => $canReadChat
                ? StaffMessageResource::collection(
                    $conversation->messages()->with(['user', 'translations', 'reactions', 'conversation'])->latest('id')->limit(self::RECENT_MESSAGES)->get()->reverse()->values()
                )->resolve()
                : null,
            'message_count' => $canReadChat ? $conversation->messages()->count() : 0,
        ];
    }
}
