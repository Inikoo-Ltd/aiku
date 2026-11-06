<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrderItemTask\GenerateJobOrderItemTasks;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\JobOrderItem;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\ArtefactStepTarget;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class AttachManufactureTaskToArtefact extends OrgAction
{
    /** @var array<int, string> */
    private array $jobsKeepingTheirCount = [];

    /**
     * Adds a step or changes one: the step given by step_id, else the step of that task with the same label.
     * The same task can be added again under another label (Mixing – Red, Mixing – Blue).
     */
    public function handle(Artefact $artefact, array $modelData): Artefact
    {
        $step = [
            'position'           => $modelData['position'] ?? 1,
            'units_per_artefact' => $modelData['units_per_artefact'] ?? 1,
        ];
        foreach (['mix_batch_kg', 'mix_shelf_life_days', 'blocks_later_steps', 'label', 'waits_for'] as $field) {
            if (array_key_exists($field, $modelData)) {
                $step[$field] = $field == 'label' ? self::cleanLabel($modelData[$field]) : $modelData[$field];
            }
        }

        $previousUnits = $artefact->recipeSteps()->pluck('units_per_artefact', 'id')->all();

        $recipeStep = self::existingStep($artefact, $modelData);
        if ($recipeStep) {
            $previousLabel = $recipeStep->label;
            $recipeStep->update($step);
            if ($recipeStep->wasChanged('label')) {
                $this->followLabel($recipeStep, $previousLabel);
            }
        } else {
            $recipeStep = ArtefactManufactureTask::create($step + [
                'artefact_id'         => $artefact->id,
                'manufacture_task_id' => $modelData['manufacture_task_id'],
            ]);
        }

        if (array_key_exists('standard_rate', $modelData) || !empty($modelData['effective_from'])) {
            SetArtefactStepTarget::run(
                $artefact,
                $recipeStep,
                array_key_exists('standard_rate', $modelData) ? $modelData['standard_rate'] : $recipeStep->standard_rate,
                $modelData['effective_from'] ?? null
            );
        }

        JobOrderItem::where('artefact_id', $artefact->id)
            ->whereHas('jobOrder', fn ($query) => $query->whereNotIn('state', [JobOrderStateEnum::RECEIVED, JobOrderStateEnum::NOT_RECEIVED]))
            ->each(fn (JobOrderItem $jobOrderItem) => GenerateJobOrderItemTasks::run($jobOrderItem, [], $previousUnits));

        $this->jobsKeepingTheirCount = GenerateJobOrderItemTasks::make()->jobsKeepingTheirCount($artefact, $previousUnits)->all();

        return $artefact;
    }

    /**
     * A step renamed keeps its target history and its job steps, which go by the step's label.
     */
    private function followLabel(ArtefactManufactureTask $recipeStep, ?string $previousLabel): void
    {
        JobOrderItemTask::where('artefact_manufacture_task_id', $recipeStep->id)->update(['label' => $recipeStep->label]);
        ArtefactStepTarget::ofStep($recipeStep->artefact_id, $recipeStep->manufacture_task_id, $previousLabel)->update(['label' => $recipeStep->label]);
    }

    public static function cleanLabel(?string $label): ?string
    {
        $label = trim((string) $label);

        return $label === '' ? null : $label;
    }

    private static function existingStep(Artefact $artefact, array $modelData): ?ArtefactManufactureTask
    {
        if (!empty($modelData['is_new_step'])) {
            return null;
        }

        if (!empty($modelData['step_id'])) {
            return $artefact->recipeSteps()->find($modelData['step_id']);
        }

        $key = ArtefactManufactureTask::key($modelData['manufacture_task_id'], self::cleanLabel($modelData['label'] ?? null));

        return $artefact->recipeSteps()->where('manufacture_task_id', $modelData['manufacture_task_id'])->get()
            ->first(fn (ArtefactManufactureTask $step) => $step->stepKey() === $key);
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
            'mix_shelf_life_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'blocks_later_steps'  => ['sometimes', 'boolean'],
            'is_new_step'         => ['sometimes', 'boolean'],
            'step_id'             => ['sometimes', 'nullable', 'integer', Rule::exists('artefacts_manufacture_tasks', 'id')->where('artefact_id', $this->artefact->id)],
            'label'               => ['sometimes', 'nullable', 'string', 'max:64'],
            'waits_for'           => ['sometimes', 'nullable', 'array'],
            'waits_for.*'         => ['integer', 'distinct', Rule::exists('artefacts_manufacture_tasks', 'id')->where('artefact_id', $this->artefact->id)],
            'effective_from'      => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'confirm_open_jobs'   => ['sometimes', 'boolean'],
            'confirm_recalculation' => ['sometimes', 'boolean'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $manufactureTask  = ManufactureTask::find($this->get('manufacture_task_id'));
        $unitsPerArtefact = $this->get('units_per_artefact', 1);
        $recipeStep       = self::existingStep($this->artefact, $this->all());
        $currentUnits     = $recipeStep?->units_per_artefact;

        if ($recipeStep && $recipeStep->manufacture_task_id != $manufactureTask->id) {
            $validator->errors()->add('manufacture_task_id', __('This step is :step, add the other task as a new step', ['step' => $recipeStep->name()]));

            return;
        }

        if ($this->has('label') || !$recipeStep) {
            $key       = ArtefactManufactureTask::key($manufactureTask->id, self::cleanLabel($this->get('label')));
            $duplicate = $this->artefact->recipeSteps()->where('manufacture_task_id', $manufactureTask->id)->get()
                ->first(fn (ArtefactManufactureTask $step) => $step->id != $recipeStep?->id && $step->stepKey() === $key);
            if ($duplicate) {
                $validator->errors()->add('label', __(':step is already in the recipe: give this one another label, e.g. a colour', ['step' => $duplicate->name()]));

                return;
            }
        }

        if ($this->has('waits_for') && $this->get('waits_for') !== null) {
            $position = (int) $this->get('position', $recipeStep?->position ?? 1);
            $awaited  = $this->artefact->recipeSteps()->whereIn('id', $this->get('waits_for'))->get();
            if ($awaited->contains(fn (ArtefactManufactureTask $step) => $step->id == $recipeStep?->id || $step->position >= $position)) {
                $validator->errors()->add('waits_for', __('A step can only wait for steps before it'));

                return;
            }
        }

        if ($this->asAction) {
            return;
        }

        if ((float) $currentUnits !== (float) $unitsPerArtefact && ArtefactManufactureTask::countsSkoPackAgain($this->artefact, (bool) $manufactureTask->is_recorded_in_kg, $unitsPerArtefact)) {
            $validator->errors()->add('units_per_artefact', ArtefactManufactureTask::skoPackCountedAgainMessage(collect([$this->artefact])));

            return;
        }

        if ($this->get('effective_from') && !filter_var($this->get('confirm_recalculation'), FILTER_VALIDATE_BOOLEAN) && $recipeStep) {
            $recalculation = SetArtefactStepTarget::make()->confirmationMessage(
                $this->artefact,
                $recipeStep,
                $this->has('standard_rate') ? $this->get('standard_rate') : $recipeStep->standard_rate,
                $this->get('effective_from')
            );
            if ($recalculation) {
                $validator->errors()->add('confirm_recalculation', $recalculation);

                return;
            }
        }

        if (filter_var($this->get('confirm_open_jobs'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $message = GenerateJobOrderItemTasks::openJobsConfirmationMessage([
            GenerateJobOrderItemTasks::make()->openJobsAffectedBy($this->artefact, [
                ($recipeStep?->stepKey() ?? ArtefactManufactureTask::key($manufactureTask->id, self::cleanLabel($this->get('label')))) => $this->get('units_per_artefact', 1),
            ]),
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

        if ($request->has('standard_rate') || $request->has('effective_from') || $request->has('mix_batch_kg') || $request->has('mix_shelf_life_days') || $request->has('blocks_later_steps') || $request->filled('label') || $request->has('waits_for')) {
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
