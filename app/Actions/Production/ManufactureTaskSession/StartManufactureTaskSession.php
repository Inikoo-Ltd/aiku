<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 21:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\Goods\ProductDevelopment\Recipe\RecordTrialRunFormulation;
use App\Actions\SysAdmin\User\GetUserCurrentEmployee;
use App\Actions\OrgAction;
use App\Actions\Production\JobOrder\ConfirmJobOrder;
use App\Actions\Production\JobOrderItemTask\UI\ShowManufactureFloor;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureBreak;
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
    // ponytail: fixed gap for "straight after", make it a production setting if the floor needs it tuned
    public const int SHORT_PREPARATION_GAP_SECONDS = 120;

    public function handle(User $user, JobOrderItemTask $jobOrderItemTask): ManufactureTaskSession
    {
        return DB::transaction(fn () => $this->startSession($user, $jobOrderItemTask));
    }

    private function startSession(User $user, JobOrderItemTask $jobOrderItemTask): ManufactureTaskSession
    {
        if (ManufactureBreak::where('user_id', $user->id)->open()->exists()) {
            throw ValidationException::withMessages([
                'job_order_item_task_id' => __('Finish your break first'),
            ]);
        }

        $group = JobOrderItemTask::find($jobOrderItemTask->id)?->combinedGroup() ?? collect();
        JobOrder::whereIn('id', $group->pluck('job_order_id'))->orderBy('id')->lockForUpdate()->get();
        $group = $group->map(fn (JobOrderItemTask $task) => JobOrderItemTask::with(['jobOrder', 'jobOrderItem', 'manufactureTask.artisans'])->find($task->id));

        if ($group->isEmpty() || $group->contains(fn (?JobOrderItemTask $task) => !$task?->jobOrder || !$task->jobOrderItem)) {
            throw ValidationException::withMessages([
                'job_order_item_task_id' => __('This job is no longer on the floor'),
            ]);
        }

        $lead     = $group->first();
        $employee = GetUserCurrentEmployee::run($user, $lead->organisation_id);
        $isMine   = $group->contains(fn (JobOrderItemTask $task) => $task->isFor($employee));

        if (!$lead->isAllowedFor($employee)) {
            throw ValidationException::withMessages([
                'job_order_item_task_id' => __('Only :names can do :step', ['names' => $lead->manufactureTask->artisans->pluck('contact_name')->implode(', '), 'step' => $lead->stepName()]),
            ]);
        }

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
            $isMineInThisOne = $jobOrderMembers->contains(fn (JobOrderItemTask $task) => $task->isFor($employee));
            if ($jobOrder->state == JobOrderStateEnum::IN_PROCESS && $isMineInThisOne && !$jobOrder->isRequestedByDevelopment()) {
                ConfirmJobOrder::make()->action($jobOrder);
            } elseif ($jobOrder->state != JobOrderStateEnum::CONFIRMED) {
                throw ValidationException::withMessages([
                    'job_order_item_task_id' => __('This job order has not been released to the floor'),
                ]);
            }

            if (RecordTrialRunFormulation::isNeededToStart($jobOrder)) {
                throw ValidationException::withMessages([
                    'job_order_item_task_id' => __('Record the formulation of this trial run first'),
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
                        ? __('Finish :step of :code first', ['step' => $blockingStep->stepName(), 'code' => $member->jobOrderItem->artefact->code])
                        : __('Finish :step first', ['step' => $blockingStep->stepName()]),
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
        $preparation = $sessionTask->manufactureTask->is_quantity_only ? null : $this->shortPreparationJustBefore($user, $sessionTask->production_id);

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
                'started_at'             => $preparation?->started_at ?? now(),
                'is_combined'            => $members->count() > 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'job_order_item_task_id' => __('You already have an open task, close it first'),
            ]);
        }

        $preparation?->update([
            'state'                 => ManufactureTaskSessionStateEnum::VOIDED,
            'moved_into_session_id' => $session->id,
            'moved_seconds'         => (int) $preparation->started_at->diffInSeconds(now()),
        ]);

        foreach ($members as $member) {
            if ($member->state == JobOrderItemTaskStateEnum::TODO) {
                $member->update(['state' => JobOrderItemTaskStateEnum::IN_PROGRESS]);
            }
        }

        return $session;
    }

    /**
     * Preparation of a few minutes closed just before this step is getting ready for it (HELP-4277): the step starts
     * when the preparation did. A break, cleaning or another day in between keeps it as Preparation.
     */
    private function shortPreparationJustBefore(User $user, int $productionId): ?ManufactureTaskSession
    {
        $last = ManufactureTaskSession::where('user_id', $user->id)
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->orderByDesc('ended_at')
            ->lockForUpdate()
            ->first();

        if (!$last
            || $last->production_id != $productionId
            || !$last->isNonProductive()
            || $last->activity_type != ManufactureTaskSessionActivityTypeEnum::PREPARATION
            || $last->ended_at->diffInSeconds(now()) > self::SHORT_PREPARATION_GAP_SECONDS
            || !$last->started_at->isSameDay(now())
            || !$last->isShortPreparation($last->ended_at)
            || ManufactureBreak::where('user_id', $user->id)->where('started_at', '>=', $last->started_at)->exists()) {
            return null;
        }

        return $last;
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
