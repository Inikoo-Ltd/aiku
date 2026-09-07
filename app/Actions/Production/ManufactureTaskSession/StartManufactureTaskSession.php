<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 21:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\SysAdmin\User\GetUserCurrentEmployee;
use App\Actions\OrgAction;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StartManufactureTaskSession extends OrgAction
{
    public function handle(User $user, JobOrderItemTask $jobOrderItemTask): ManufactureTaskSession
    {
        if ($jobOrderItemTask->jobOrder()->first()->state != JobOrderStateEnum::CONFIRMED) {
            throw ValidationException::withMessages([
                'job_order_item_task_id' => __('This job order has not been released to the floor'),
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

        $session = ManufactureTaskSession::create([
            'group_id'               => $jobOrderItemTask->group_id,
            'organisation_id'        => $jobOrderItemTask->organisation_id,
            'production_id'          => $jobOrderItemTask->production_id,
            'job_order_item_task_id' => $jobOrderItemTask->id,
            'manufacture_task_id'    => $jobOrderItemTask->manufacture_task_id,
            'user_id'                => $user->id,
            'employee_id'            => GetUserCurrentEmployee::run($user, $jobOrderItemTask->organisation_id)?->id,
            'state'                  => ManufactureTaskSessionStateEnum::OPEN,
            'started_at'             => now(),
        ]);

        if ($jobOrderItemTask->state == JobOrderItemTaskStateEnum::TODO) {
            $jobOrderItemTask->update(['state' => JobOrderItemTaskStateEnum::IN_PROGRESS]);
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
