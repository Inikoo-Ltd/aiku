<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 16:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItemTask;

use App\Actions\OrgAction;
use App\Events\BroadcastManufactureFloorChanged;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SeparateJobOrderItemTasks extends OrgAction
{
    /**
     * The lines go back to being worked one by one. What was already made together stays with each
     * line as it was shared out.
     */
    public function handle(JobOrderItemTask $jobOrderItemTask): JobOrderItemTask
    {
        if (!$jobOrderItemTask->combined_task_id) {
            return $jobOrderItemTask;
        }

        $groupIds = JobOrderItemTask::where('combined_task_id', $jobOrderItemTask->combined_task_id)->pluck('id');

        if (ManufactureTaskSession::whereIn('job_order_item_task_id', $groupIds)->where('state', ManufactureTaskSessionStateEnum::OPEN)->exists()) {
            throw ValidationException::withMessages([
                'job_order_item_task_id' => __('Someone is working on this combined task, separate it when they finish.'),
            ]);
        }

        JobOrderItemTask::whereIn('id', $groupIds)->update(['combined_task_id' => null]);

        BroadcastManufactureFloorChanged::dispatch($jobOrderItemTask->production_id);

        return $jobOrderItemTask->refresh();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function asController(JobOrderItemTask $jobOrderItemTask, ActionRequest $request): JobOrderItemTask
    {
        $this->initialisationFromProduction($jobOrderItemTask->production, $request);

        return $this->handle($jobOrderItemTask);
    }

    public function action(JobOrderItemTask $jobOrderItemTask): JobOrderItemTask
    {
        $this->asAction = true;
        $this->initialisationFromProduction($jobOrderItemTask->production, []);

        return $this->handle($jobOrderItemTask);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
