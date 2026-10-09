<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 21:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\SysAdmin\User\GetUserCurrentEmployee;
use App\Actions\OrgAction;
use App\Actions\Production\JobOrder\ConfirmJobOrder;
use App\Actions\Production\JobOrderItemTask\UI\ShowManufactureFloor;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use App\Models\SysAdmin\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StartManufactureTaskSession extends OrgAction
{
    public function handle(User $user, JobOrderItemTask $jobOrderItemTask): ManufactureTaskSession
    {
        return DB::transaction(fn () => $this->startSession($user, $jobOrderItemTask));
    }

    private function startSession(User $user, JobOrderItemTask $jobOrderItemTask): ManufactureTaskSession
    {
        $group = JobOrderItemTask::find($jobOrderItemTask->id)?->combinedGroup() ?? collect();
        JobOrder::whereIn('id', $group->pluck('job_order_id'))->orderBy('id')->lockForUpdate()->get();
        $group = $group->map(fn (JobOrderItemTask $task) => JobOrderItemTask::with(['jobOrder', 'jobOrderItem'])->find($task->id));

        if ($group->isEmpty() || $group->contains(fn (?JobOrderItemTask $task) => !$task?->jobOrder || !$task->jobOrderItem)) {
            throw ValidationException::withMessages([
                'job_order_item_task_id' => __('This job is no longer on the floor'),
            ]);
        }

        $lead     = $group->first();
        $employee = GetUserCurrentEmployee::run($user, $lead->organisation_id);
        $isMine   = $employee && $group->contains(fn (JobOrderItemTask $task) => ($task->jobOrderItem->employee_id ?? $task->jobOrder->employee_id) == $employee->id);

        if (!$isMine && !ShowManufactureFloor::canPickOpenJobs($user, $lead->production)) {
            throw ValidationException::withMessages([
                'job_order_item_task_id' => __('This job is not addressed to you'),
            ]);
        }

        $members = $group->reject(fn (JobOrderItemTask $task) => $task->state == JobOrderItemTaskStateEnum::DONE)->values();
        if ($members->isEmpty()) {
            throw ValidationException::withMessages([
                'job_order_item_task_id' => __('This task is already finished'),
            ]);
        }

        foreach ($members->groupBy('job_order_id') as $jobOrderMembers) {
            $jobOrder        = $jobOrderMembers->first()->jobOrder;
            $isMineInThisOne = $employee && $jobOrderMembers->contains(fn (JobOrderItemTask $task) => ($task->jobOrderItem->employee_id ?? $jobOrder->employee_id) == $employee->id);
            if ($jobOrder->state == JobOrderStateEnum::IN_PROCESS && $isMineInThisOne) {
                ConfirmJobOrder::make()->action($jobOrder);
            } elseif ($jobOrder->state != JobOrderStateEnum::CONFIRMED) {
                throw ValidationException::withMessages([
                    'job_order_item_task_id' => __('This job order has not been released to the floor'),
                ]);
            }
        }

        foreach ($members as $member) {
            $blockingStep = $member->state == JobOrderItemTaskStateEnum::TODO
                ? $member->blockingStep($member->jobOrderItem->tasks)
                : null;
            if ($blockingStep) {
                throw ValidationException::withMessages([
                    'job_order_item_task_id' => $members->count() > 1
                        ? __('Finish :step of :code first', ['step' => $blockingStep->manufactureTask->name, 'code' => $member->jobOrderItem->artefact->code])
                        : __('Finish :step first', ['step' => $blockingStep->manufactureTask->name]),
                ]);
            }
        }

        $takenByAnotherUser = ManufactureTaskSession::whereIn('job_order_item_task_id', $group->pluck('id'))
            ->where('state', ManufactureTaskSessionStateEnum::OPEN)
            ->where('user_id', '!=', $user->id)
            ->exists();
        if ($takenByAnotherUser) {
            throw ValidationException::withMessages([
                'job_order_item_task_id' => __('Someone else is already working on this step'),
            ]);
        }

        $openSession = ManufactureTaskSession::where('user_id', $user->id)
            ->where('state', ManufactureTaskSessionStateEnum::OPEN)
            ->first();
        if ($openSession) {
            throw ValidationException::withMessages([
                'job_order_item_task_id' => __('You already have an open task, close it first'),
            ]);
        }

        $sessionTask = $members->first();

        try {
            $session = ManufactureTaskSession::create([
                'group_id'               => $sessionTask->group_id,
                'organisation_id'        => $sessionTask->organisation_id,
                'production_id'          => $sessionTask->production_id,
                'job_order_item_task_id' => $sessionTask->id,
                'manufacture_task_id'    => $sessionTask->manufacture_task_id,
                'user_id'                => $user->id,
                'employee_id'            => $employee?->id,
                'state'                  => ManufactureTaskSessionStateEnum::OPEN,
                'started_at'             => now(),
                'is_combined'            => $members->count() > 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'job_order_item_task_id' => __('You already have an open task, close it first'),
            ]);
        }

        foreach ($members as $member) {
            if ($member->state == JobOrderItemTaskStateEnum::TODO) {
                $member->update(['state' => JobOrderItemTaskStateEnum::IN_PROGRESS]);
            }
        }

        return $session;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function action(User $user, JobOrderItemTask $jobOrderItemTask): ManufactureTaskSession
    {
        $this->asAction = true;
        $this->initialisation($jobOrderItemTask->organisation, []);

        return $this->handle($user, $jobOrderItemTask);
    }

    public function asController(JobOrderItemTask $jobOrderItemTask, ActionRequest $request): ManufactureTaskSession
    {
        $this->initialisationFromProduction($jobOrderItemTask->production, $request);

        return $this->handle($request->user(), $jobOrderItemTask);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
