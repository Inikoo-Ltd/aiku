<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrderItemTask\GenerateJobOrderItemTasks;
use App\Actions\Production\ManufactureTaskSession\RecalculateUntargetedManufactureTaskSessions;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\JobOrderItem;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\ManufactureTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class AttachManufactureTaskToArtefact extends OrgAction
{
    /** @var array<int, string> */
    private array $jobsKeepingTheirCount = [];

    public function handle(Artefact $artefact, array $modelData): Artefact
    {
        $step = [
            'position'           => $modelData['position'] ?? 1,
            'units_per_artefact' => $modelData['units_per_artefact'] ?? 1,
        ];
        if (array_key_exists('standard_rate', $modelData)) {
            $step['standard_rate'] = $modelData['standard_rate'];
        }
        if (array_key_exists('mix_batch_kg', $modelData)) {
            $step['mix_batch_kg'] = $modelData['mix_batch_kg'];
        }
        if (array_key_exists('blocks_later_steps', $modelData)) {
            $step['blocks_later_steps'] = $modelData['blocks_later_steps'];
        }

        $previousUnits = $artefact->manufactureTasks()->get()->mapWithKeys(fn (ManufactureTask $manufactureTask) => [$manufactureTask->id => $manufactureTask->pivot->units_per_artefact])->all();

        $artefact->manufactureTasks()->syncWithoutDetaching([$modelData['manufacture_task_id'] => $step]);

        if (!empty($step['standard_rate'])) {
            RecalculateUntargetedManufactureTaskSessions::run($artefact);
        }

        JobOrderItem::where('artefact_id', $artefact->id)
            ->whereHas('jobOrder', fn ($query) => $query->whereNotIn('state', [JobOrderStateEnum::RECEIVED, JobOrderStateEnum::NOT_RECEIVED]))
            ->each(fn (JobOrderItem $jobOrderItem) => GenerateJobOrderItemTasks::run($jobOrderItem, [], $previousUnits));

        $this->jobsKeepingTheirCount = GenerateJobOrderItemTasks::make()->jobsKeepingTheirCount($artefact, $previousUnits)->all();

        return $artefact;
    }

    public function rules(): array
    {
        return [
            'manufacture_task_id' => [
                'required',
                Rule::exists('manufacture_tasks', 'id')->where('production_id', $this->artefact->production_id),
            ],
            'position'            => ['sometimes', 'integer', 'min:1'],
            'units_per_artefact'  => ['sometimes', 'numeric', 'gt:0'],
            'standard_rate'       => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'mix_batch_kg'        => ['sometimes', 'nullable', Rule::in($this->artefact->production->mixBatchSizesKg())],
            'blocks_later_steps'  => ['sometimes', 'boolean'],
            'confirm_open_jobs'   => ['sometimes', 'boolean'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->asAction || $validator->errors()->isNotEmpty()) {
            return;
        }

        $manufactureTask = ManufactureTask::find($this->get('manufacture_task_id'));
        $unitsPerArtefact = $this->get('units_per_artefact', 1);
        $currentUnits     = $this->artefact->manufactureTasks()->find($manufactureTask->id)?->pivot->units_per_artefact;

        if ((float) $currentUnits !== (float) $unitsPerArtefact && ArtefactManufactureTask::countsSkoPackAgain($this->artefact, (bool) $manufactureTask->is_recorded_in_kg, $unitsPerArtefact)) {
            $validator->errors()->add('units_per_artefact', ArtefactManufactureTask::skoPackCountedAgainMessage(collect([$this->artefact])));

            return;
        }

        if (filter_var($this->get('confirm_open_jobs'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $message = GenerateJobOrderItemTasks::openJobsConfirmationMessage([
            GenerateJobOrderItemTasks::make()->openJobsAffectedBy($this->artefact, [(int) $this->get('manufacture_task_id') => $this->get('units_per_artefact', 1)]),
        ]);

        if ($message) {
            $validator->errors()->add('confirm_open_jobs', $message);
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->has('standard_rate') || $request->has('mix_batch_kg') || $request->has('blocks_later_steps')) {
            return $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.edit"]);
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    private Artefact $artefact;

    public function action(Artefact $artefact, array $modelData): Artefact
    {
        $this->asAction = true;
        $this->artefact = $artefact;
        $this->initialisation($artefact->organisation, $modelData);

        return $this->handle($artefact, $this->validatedData);
    }

    public function asController(Artefact $artefact, ActionRequest $request): Artefact
    {
        $this->artefact = $artefact;
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($artefact, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        if (!$this->jobsKeepingTheirCount) {
            return back();
        }

        return back()->with('notification', [
            'status'      => 'warning',
            'title'       => __('Jobs already worked on keep their old count'),
            'description' => __('Work is already recorded on these with the previous units, check them on the job order: :jobs', [
                'jobs' => implode(', ', $this->jobsKeepingTheirCount),
            ]),
        ]);
    }
}
