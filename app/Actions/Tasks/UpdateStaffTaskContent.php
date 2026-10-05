<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Mon, 05 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Tasks;

use App\Events\BroadcastStaffTaskChanged;
use App\Http\Resources\Tasks\StaffTaskResource;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The person who raised a task, or a supervisor, rewrites its subject and description and removes or adds the task's own
 * files; every change lands in the task history.
 */
class UpdateStaffTaskContent
{
    use AsAction;

    /**
     * @param array{subject?: string, description?: string|null, remove_media?: array<int, string>, images?: array<int, \Illuminate\Http\UploadedFile>} $modelData
     */
    public function handle(StaffTask $task, array $modelData): StaffTask
    {
        $files = $task->replaceOwnTicketFiles(Arr::get($modelData, 'remove_media', []), Arr::get($modelData, 'images', []));

        $task->update(Arr::only($modelData, ['subject', 'description']));
        $task->recordTicketFilesInHistory($files['removed'], $files['added']);

        BroadcastStaffTaskChanged::dispatch($task);

        return $task;
    }

    public function rules(): array
    {
        return [
            'subject'        => ['sometimes', 'required', 'string', 'max:255'],
            'description'    => ['sometimes', 'nullable', 'string', 'max:5000'],
            'remove_media'   => ['sometimes', 'array'],
            'remove_media.*' => ['string'],
            'images'         => ['sometimes', 'array', 'max:5'],
            'images.*'       => StaffTask::ticketFileRules(),
        ];
    }

    public function getValidationMessages(): array
    {
        return StaffTask::ticketFileValidationMessages();
    }

    public function getValidationAttributes(): array
    {
        return StaffTask::ticketFileValidationAttributes(request()->file('images', []));
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffTask')->canEditContentBy($request->user());
    }

    public function asController(StaffTask $staffTask, ActionRequest $request): StaffTaskResource
    {
        $task = $this->handle($staffTask, $request->validated());

        return new StaffTaskResource($task->load(['requester', 'assignee', 'collaborators.image', 'conversation.participants', 'model']));
    }
}
