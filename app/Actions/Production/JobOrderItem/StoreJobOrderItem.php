<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 12:09:58 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItem;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrderItemTask\GenerateJobOrderItemTasks;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItem\JobOrderItemStateEnum;
use App\Enums\Production\JobOrderItem\JobOrderItemStatusEnum;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreJobOrderItem extends OrgAction
{
    public function handle(JobOrder $jobOrder, array $modelData): JobOrderItem
    {
        if (Arr::get($modelData, 'notes') === null) {
            data_set($modelData, 'notes', '');
        }

        if (Arr::exists($modelData, 'state') and Arr::get($modelData, 'state') != JobOrderItemStateEnum::IN_PROCESS) {
            if (!Arr::get($modelData, 'reference')) {
                data_set(
                    $modelData,
                    'reference',
                    $jobOrder->reference.'-'.($jobOrder->jobOrderItems()->count() + 1)
                );
            }
        }

        data_set($modelData, 'group_id', $jobOrder->group_id);
        data_set($modelData, 'organisation_id', $jobOrder->organisation_id);

        /** @var JobOrderItem $jobOrderItem */
        $jobOrderItem = $jobOrder->jobOrderItems()->create($modelData);

        if ($jobOrderItem->reference) {
            $jobOrderItem->generateSlug();
            $jobOrderItem->save();
        }
        $jobOrderItem->refresh();

        if (!in_array($jobOrder->state, [JobOrderStateEnum::RECEIVED, JobOrderStateEnum::NOT_RECEIVED])) {
            GenerateJobOrderItemTasks::run($jobOrderItem);
        }

        return $jobOrderItem;
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


    public function rules(): array
    {
        return [
            'artefact_id'        => ['required', 'integer', 'exists:artefacts,id'],
            'status'             => [
                'sometimes',
                Rule::enum(JobOrderItemStatusEnum::class)
            ],
            'state'              => [
                'sometimes',
                Rule::enum(JobOrderItemStateEnum::class)
            ],
            'notes'              => ['sometimes', 'nullable', 'string', 'max:1024'],
            'quantity'           => ['required', 'integer', 'min:1'],
            'created_at'         => ['sometimes', 'date'],
            'received_at'        => ['sometimes', 'nullable', 'date'],
            'source_id'          => ['sometimes', 'nullable', 'string'],
        ];
    }


    public function asController(JobOrder $jobOrder, ActionRequest $request): JobOrderItem
    {
        $this->initialisationFromProduction($jobOrder->production, $request);

        return $this->handle($jobOrder, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }


    public function action(JobOrder $jobOrder, array $modelData): JobOrderItem
    {
        $this->asAction = true;
        $this->initialisation($jobOrder->organisation, $modelData);

        return $this->handle($jobOrder, $this->validatedData);
    }


}
