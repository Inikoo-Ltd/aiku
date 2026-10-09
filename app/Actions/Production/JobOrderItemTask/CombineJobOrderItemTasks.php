<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 16:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItemTask;

use App\Actions\OrgAction;
use App\Events\BroadcastManufactureFloorChanged;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class CombineJobOrderItemTasks extends OrgAction
{
    /**
     * One batch for several job lines: the same step of each line is worked as a single task on the
     * floor, and closing it hands every line its share of the quantity and the time.
     *
     * @param  int[]  $taskIds
     */
    public function handle(Production $production, array $taskIds): JobOrderItemTask
    {
        $lead = DB::transaction(function () use ($taskIds) {
            $tasks = $this->tasks($taskIds);
            $lead  = $tasks->first();

            JobOrderItemTask::whereIn('id', $tasks->pluck('id'))->update(['combined_task_id' => $lead->id]);

            return $lead->refresh();
        });

        BroadcastManufactureFloorChanged::dispatch($production->id);

        return $lead;
    }

    /**
     * @param  int[]  $taskIds
     * @return Collection<int, JobOrderItemTask>
     */
    private function tasks(array $taskIds): Collection
    {
        return JobOrderItemTask::whereIn('id', $taskIds)
            ->with(['jobOrder', 'jobOrderItem'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
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

    public function rules(): array
    {
        return [
            'job_order_item_task_ids'   => ['required', 'array', 'min:2'],
            'job_order_item_task_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $taskIds = $this->get('job_order_item_task_ids');
        $tasks   = $this->tasks($taskIds);

        $isLive = fn (JobOrderItemTask $task) => $task->production_id == $this->production->id
            && $task->jobOrder
            && $task->jobOrderItem
            && in_array($task->jobOrder->state, JobOrderStateEnum::open());

        if ($tasks->count() != count($taskIds) || !$tasks->every($isLive)) {
            $validator->errors()->add('job_order_item_task_ids', __('These jobs changed meanwhile, reload the page.'));

            return;
        }

        if ($tasks->pluck('manufacture_task_id')->unique()->count() > 1) {
            $validator->errors()->add('job_order_item_task_ids', __('Only the same step can be combined.'));

            return;
        }

        if ($tasks->contains(fn (JobOrderItemTask $task) => $task->state == JobOrderItemTaskStateEnum::DONE)) {
            $validator->errors()->add('job_order_item_task_ids', __('A finished step cannot be combined.'));

            return;
        }

        if ($tasks->contains(fn (JobOrderItemTask $task) => $task->combinedGroup()->count() > 1)) {
            $validator->errors()->add('job_order_item_task_ids', __('One of these steps is already combined, separate it first.'));

            return;
        }

        if (ManufactureTaskSession::whereIn('job_order_item_task_id', $tasks->pluck('id'))->where('state', ManufactureTaskSessionStateEnum::OPEN)->exists()) {
            $validator->errors()->add('job_order_item_task_ids', __('Someone is working on one of these steps, combine them when they finish.'));
        }
    }

    public function asController(Production $production, ActionRequest $request): JobOrderItemTask
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData['job_order_item_task_ids']);
    }

    /**
     * @param  int[]  $taskIds
     */
    public function action(Production $production, array $taskIds): JobOrderItemTask
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, ['job_order_item_task_ids' => $taskIds]);

        return $this->handle($production, $this->validatedData['job_order_item_task_ids']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
