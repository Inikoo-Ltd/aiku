<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItemTask;

use App\Actions\OrgAction;
use App\Models\Production\JobOrderItemTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class AssignJobOrderItemTask extends OrgAction
{
    private JobOrderItemTask $jobOrderItemTask;

    /**
     * Names the operator of one step, or of every step made with it as one batch. Only decides who
     * sees the step on the floor: sessions go on recording who did the work.
     */
    public function handle(JobOrderItemTask $jobOrderItemTask, ?int $employeeId): JobOrderItemTask
    {
        $jobOrderItemTask->combinedGroup()->each(fn (JobOrderItemTask $task) => $task->update(['employee_id' => $employeeId]));

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

    public function rules(): array
    {
        return [
            'employee_id' => ['present', 'nullable', 'integer', Rule::exists('employees', 'id')->where('organisation_id', $this->organisation->id)],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $employeeId = $this->get('employee_id');
        $allowed    = $this->jobOrderItemTask->manufactureTask->artisans;
        if ($employeeId && $allowed->isNotEmpty() && !$allowed->contains('id', $employeeId)) {
            $validator->errors()->add('employee_id', __('Only :names can do :step', ['names' => $allowed->pluck('contact_name')->implode(', '), 'step' => $this->jobOrderItemTask->stepName()]));
        }
    }

    public function action(JobOrderItemTask $jobOrderItemTask, ?int $employeeId): JobOrderItemTask
    {
        $this->asAction         = true;
        $this->jobOrderItemTask = $jobOrderItemTask;
        $this->initialisation($jobOrderItemTask->organisation, ['employee_id' => $employeeId]);

        return $this->handle($jobOrderItemTask, $this->validatedData['employee_id']);
    }

    public function asController(JobOrderItemTask $jobOrderItemTask, ActionRequest $request): JobOrderItemTask
    {
        $this->jobOrderItemTask = $jobOrderItemTask;
        $this->initialisationFromProduction($jobOrderItemTask->production, $request);

        return $this->handle($jobOrderItemTask, $this->validatedData['employee_id']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
