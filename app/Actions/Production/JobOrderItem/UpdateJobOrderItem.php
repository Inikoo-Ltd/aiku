<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 23:07:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItem;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrderItemTask\CalculateJobOrderItemTaskQuantities;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItem\JobOrderItemStateEnum;
use App\Enums\Production\JobOrderItem\JobOrderItemStatusEnum;
use App\Models\CRM\WebUser;
use App\Models\Production\JobOrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateJobOrderItem extends OrgAction
{
    use WithActionUpdate;


    private JobOrderItem $jobOrderItem;

    public function handle(JobOrderItem $jobOrderItem, array $modelData): JobOrderItem
    {
        $jobOrderItem = $this->update($jobOrderItem, $modelData, ['data']);

        if ($jobOrderItem->wasChanged('quantity')) {
            $unitsPerArtefact = $jobOrderItem->artefact->manufactureTasks()->get()->pluck('pivot.units_per_artefact', 'id');

            foreach ($jobOrderItem->tasks as $task) {
                $task->update(['quantity_required' => $jobOrderItem->quantity * ($unitsPerArtefact[$task->manufacture_task_id] ?? 1)]);
                CalculateJobOrderItemTaskQuantities::run($task);
            }
        }

        return $jobOrderItem;
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->asAction || !$this->has('quantity')) {
            return;
        }

        if (!in_array($this->jobOrderItem->jobOrder->state, JobOrderStateEnum::open()) || $this->jobOrderItem->quantity_received > 0) {
            $validator->errors()->add('quantity', __('This job is already being received, its quantity can no longer change.'));

            return;
        }

        $unitsPerArtefact = $this->jobOrderItem->artefact->manufactureTasks()->get()->pluck('pivot.units_per_artefact', 'id');
        foreach ($this->jobOrderItem->tasks as $task) {
            if ($this->get('quantity') * ($unitsPerArtefact[$task->manufacture_task_id] ?? 1) < $task->quantity_made) {
                $validator->errors()->add('quantity', __('More than that is already made, the quantity cannot go below it.'));

                return;
            }
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->jobOrderItem->jobOrder->production_id}.orchestrate",
        ]);
    }

    public function rules(): array
    {
        return [
            'status'             => [
                'sometimes',
                Rule::enum(JobOrderItemStatusEnum::class)
            ],
            'state'              => [
                'sometimes',
                Rule::enum(JobOrderItemStateEnum::class)
            ],
            'notes'              => ['sometimes', 'nullable', 'string', 'max:1024'],
            'quantity'           => ['sometimes', 'integer', 'min:1'],
            'received_at'        => ['sometimes', 'nullable', 'date'],
        ];
    }


    public function asController(JobOrderItem $jobOrderItem, ActionRequest $request): JobOrderItem
    {
        $this->jobOrderItem = $jobOrderItem;
        $this->initialisation($jobOrderItem->organisation, $request);

        return $this->handle($jobOrderItem, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }


    public function action(JobOrderItem $jobOrderItem, array $modelData, int $hydratorsDelay = 0): JobOrderItem
    {
        $this->jobOrderItem         = $jobOrderItem;
        $this->asAction             = true;
        $this->hydratorsDelay       = $hydratorsDelay;
        $this->initialisation($jobOrderItem->organisation, $modelData);

        return $this->handle($jobOrderItem, $this->validatedData);
    }


}
