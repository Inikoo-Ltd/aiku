<?php

/*
 * Author Louis Perez
 * Created on 29-09-2026-15h-03m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Actions\Production\RawMaterial\Hydrators\RawMaterialHydrateFromOrgStock;
use App\Actions\Production\JobOrderItemTask\GenerateJobOrderItemTasks;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\JobOrderItem;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class SetArtefactsRecipe extends OrgAction
{
    /** @var array<int, string> */
    private array $jobsKeepingTheirCount = [];

    /**
     * Gives every selected artefact exactly the steps sent: steps they already have are updated, new ones are added,
     * and steps missing from the list are removed together with their raw materials. A step is its task and label, so
     * the same task can be sent more than once with different labels; waits_for lists the indexes, in steps, of the
     * earlier steps it waits for (null or left out: the earlier steps that hold up later ones). A step's raw materials go to every
     * artefact unless that artefact has its own list in artefact_raw_materials.
     *
     * @param array{
     *     artefacts: array<int, int>,
     *     steps: array<int, array{
     *         manufacture_task_id: int,
     *         label?: string|null,
     *         waits_for?: array<int, int>|null,
     *         position: int,
     *         units_per_artefact: float|int|string,
 *         standard_rate?: float|int|string|null,
     *         effective_from?: string|null,
     *         raw_materials?: array<int, array{raw_material_id: int, quantity_per_unit: float|int|string}>,
     *         artefact_raw_materials?: array<int, array{
     *             artefact_id: int,
     *             raw_materials: array<int, array{raw_material_id: int, quantity_per_unit: float|int|string}>
     *         }>
     *     }>
     * } $modelData
     */
    public function handle(Production $production, array $modelData): int
    {
        $artefacts = Artefact::where('production_id', $production->id)
            ->whereIn('id', $modelData['artefacts'])
            ->get();

        $steps = collect($modelData['steps']);

        DB::transaction(function () use ($artefacts, $steps) {
            $artefacts->each(fn (Artefact $artefact) => $this->setRecipe($artefact, $steps));
        });

        return $artefacts->count();
    }

    private function setRecipe(Artefact $artefact, Collection $steps): void
    {
        $previousSteps = $artefact->recipeSteps()->get();
        $previousUnits = $previousSteps->pluck('units_per_artefact', 'id')->all();
        $previousByKey = $previousSteps->keyBy(fn (ArtefactManufactureTask $step) => $step->stepKey());
        $stepKeys      = $steps->map(fn (array $step) => ArtefactManufactureTask::key($step['manufacture_task_id'], AttachManufactureTaskToArtefact::cleanLabel($step['label'] ?? null)));
        $removedSteps  = $previousSteps->reject(fn (ArtefactManufactureTask $step) => $stepKeys->contains($step->stepKey()));
        $lastPosition  = $steps->max('position');

        $recipeSteps   = [];
        $replacedSteps = [];
        foreach ($steps as $index => $step) {
            $values = [
                'position'           => $step['position'],
                'units_per_artefact' => $step['units_per_artefact'],
                'label'              => AttachManufactureTaskToArtefact::cleanLabel($step['label'] ?? null),
            ] + (array_key_exists('blocks_later_steps', $step) ? ['blocks_later_steps' => $step['blocks_later_steps']] : []);

            $recipeStep = $previousByKey->get($stepKeys[$index]);
            if ($recipeStep) {
                $recipeStep->update($values);
            } else {
                $recipeStep = ArtefactManufactureTask::create($values + ['artefact_id' => $artefact->id, 'manufacture_task_id' => $step['manufacture_task_id']]);
                $replaced   = $step['position'] < $lastPosition ? $removedSteps->first(fn (ArtefactManufactureTask $removed) => $removed->position == $step['position']) : null;
                if ($replaced) {
                    $replacedSteps[$recipeStep->id] = $replaced->id;
                }
            }
            $recipeSteps[$index] = $recipeStep;
        }

        foreach ($steps as $index => $step) {
            if (array_key_exists('waits_for', $step)) {
                $recipeSteps[$index]->update(['waits_for' => $step['waits_for'] === null ? null : collect($step['waits_for'])->map(fn (int $awaited) => $recipeSteps[$awaited]->id)->values()->all()]);
            }

            SetArtefactStepTarget::run($artefact, $recipeSteps[$index], $step['standard_rate'] ?? null, $step['effective_from'] ?? null);

            $rawMaterials = collect($this->rawMaterialsFor($artefact, $step));

            $recipeSteps[$index]->rawMaterials()->whereNotIn('raw_material_id', $rawMaterials->pluck('raw_material_id'))->delete();
            $rawMaterials->each(fn (array $rawMaterial) => AttachRawMaterialToRecipeStep::make()->handle($recipeSteps[$index], $rawMaterial));
        }

        RawMaterialHydrateFromOrgStock::dispatchIntermediatesOf($artefact->id);

        JobOrderItem::where('artefact_id', $artefact->id)
            ->whereHas('jobOrder', fn ($query) => $query->whereNotIn('state', [JobOrderStateEnum::RECEIVED, JobOrderStateEnum::NOT_RECEIVED]))
            ->each(fn (JobOrderItem $jobOrderItem) => GenerateJobOrderItemTasks::run($jobOrderItem, $replacedSteps, $previousUnits));

        $this->jobsKeepingTheirCount = [...$this->jobsKeepingTheirCount, ...GenerateJobOrderItemTasks::make()->jobsKeepingTheirCount($artefact, $previousUnits)];

        $removedSteps->each(fn (ArtefactManufactureTask $removed) => DetachManufactureTaskFromArtefact::make()->handle($artefact, $removed));
    }

    private function rawMaterialsFor(Artefact $artefact, array $step): array
    {
        $override = collect($step['artefact_raw_materials'] ?? [])->firstWhere('artefact_id', $artefact->id);

        return $override ? $override['raw_materials'] : ($step['raw_materials'] ?? []);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.edit"]);
    }

    public function rules(): array
    {
        return [
            'artefacts'                                                          => ['required', 'array', 'min:1'],
            'artefacts.*'                                                        => ['integer'],
            'steps'                                                              => ['required', 'array', 'min:1'],
            'steps.*.manufacture_task_id'                                        => [
                'required',
                'integer',
                Rule::exists('manufacture_tasks', 'id')->where('production_id', $this->production->id),
            ],
            'steps.*.position'                                                   => ['required', 'integer', 'min:1'],
            'steps.*.units_per_artefact'                                         => ['required', 'numeric', 'gt:0'],
            'steps.*.standard_rate'                                              => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'steps.*.effective_from'                                             => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'steps.*.blocks_later_steps'                                         => ['sometimes', 'boolean'],
            'steps.*.label'                                                      => ['sometimes', 'nullable', 'string', 'max:64'],
            'steps.*.waits_for'                                                  => ['sometimes', 'nullable', 'array'],
            'steps.*.waits_for.*'                                                => ['integer', 'min:0'],
            'steps.*.raw_materials'                                              => ['sometimes', 'array'],
            'steps.*.raw_materials.*.raw_material_id'                            => [
                'required',
                'integer',
                Rule::exists('raw_materials', 'id')->where('organisation_id', $this->organisation->id),
            ],
            'steps.*.raw_materials.*.quantity_per_unit'                          => ['required', 'numeric', 'gt:0'],
            'steps.*.artefact_raw_materials'                                     => ['sometimes', 'array'],
            'steps.*.artefact_raw_materials.*.artefact_id'                       => ['required', 'integer'],
            'steps.*.artefact_raw_materials.*.raw_materials'                     => ['present', 'array'],
            'steps.*.artefact_raw_materials.*.raw_materials.*.raw_material_id'   => [
                'required',
                'integer',
                Rule::exists('raw_materials', 'id')->where('organisation_id', $this->organisation->id),
            ],
            'steps.*.artefact_raw_materials.*.raw_materials.*.quantity_per_unit' => ['required', 'numeric', 'gt:0'],
            'confirm_open_jobs'                                                  => ['sometimes', 'boolean'],
            'confirm_recalculation'                                              => ['sometimes', 'boolean'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $steps    = collect($this->get('steps'));
        $stepKeys = $steps->map(fn (array $step) => ArtefactManufactureTask::key($step['manufacture_task_id'], AttachManufactureTaskToArtefact::cleanLabel($step['label'] ?? null)));
        if ($stepKeys->duplicates()->isNotEmpty()) {
            $validator->errors()->add('steps', __('A task used more than once in a recipe needs a different label on each, e.g. a colour'));

            return;
        }

        foreach ($steps as $index => $step) {
            foreach ($step['waits_for'] ?? [] as $awaited) {
                if (!isset($steps[$awaited]) || $awaited == $index || $steps[$awaited]['position'] >= $step['position']) {
                    $validator->errors()->add('steps', __('A step can only wait for steps before it'));

                    return;
                }
            }
        }

        if ($this->asAction) {
            return;
        }

        $kgTaskIds = ManufactureTask::whereIn('id', $steps->pluck('manufacture_task_id'))->where('is_recorded_in_kg', true)->pluck('id');
        $artefacts = Artefact::where('production_id', $this->production->id)
            ->whereIn('id', $this->get('artefacts'))
            ->with(['orgStock', 'manufactureTasks', 'recipeSteps'])
            ->get();

        $packCountedAgain = $artefacts->filter(fn (Artefact $artefact) => $steps->contains(
            fn (array $step, int $index) => (float) $artefact->recipeSteps->first(fn (ArtefactManufactureTask $recipeStep) => $recipeStep->stepKey() === $stepKeys[$index])?->units_per_artefact !== (float) $step['units_per_artefact']
                && ArtefactManufactureTask::countsSkoPackAgain($artefact, $kgTaskIds->contains($step['manufacture_task_id']), $step['units_per_artefact'])
        ));
        if ($packCountedAgain->isNotEmpty()) {
            $validator->errors()->add('steps', ArtefactManufactureTask::skoPackCountedAgainMessage($packCountedAgain));

            return;
        }

        if (!filter_var($this->get('confirm_recalculation'), FILTER_VALIDATE_BOOLEAN)) {
            $recalculations = $steps->filter(fn (array $step) => !empty($step['effective_from']))
                ->flatMap(fn (array $step, int $index) => $artefacts
                    ->map(fn (Artefact $artefact) => [$artefact, $artefact->recipeSteps->first(fn (ArtefactManufactureTask $recipeStep) => $recipeStep->stepKey() === $stepKeys[$index])])
                    ->filter(fn (array $artefactStep) => $artefactStep[1] !== null)
                    ->map(fn (array $artefactStep) => ($message = SetArtefactStepTarget::make()->confirmationMessage($artefactStep[0], $artefactStep[1], $step['standard_rate'] ?? null, $step['effective_from']))
                        ? "{$artefactStep[0]->code}: {$message}"
                        : null))
                ->filter();
            if ($recalculations->isNotEmpty()) {
                $validator->errors()->add('confirm_recalculation', $recalculations->implode("\n"));

                return;
            }
        }

        if (filter_var($this->get('confirm_open_jobs'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $newUnits = $steps->mapWithKeys(fn (array $step, int $index) => [$stepKeys[$index] => $step['units_per_artefact']])->all();

        $message = GenerateJobOrderItemTasks::openJobsConfirmationMessage(
            $artefacts
                ->map(fn (Artefact $artefact) => GenerateJobOrderItemTasks::make()->openJobsAffectedBy(
                    $artefact,
                    $newUnits,
                    $artefact->recipeSteps->map(fn (ArtefactManufactureTask $recipeStep) => $recipeStep->stepKey())->diff(array_keys($newUnits))->values()->all()
                ))
                ->all()
        );

        if ($message) {
            $validator->errors()->add('confirm_open_jobs', $message);
        }
    }

    public function getValidationMessages(): array
    {
        return [
            'steps.required'                         => __('Add at least one step'),
            'steps.*.manufacture_task_id.required'   => __('Pick a task for every step'),
        ];
    }

    public function action(Production $production, array $modelData): int
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    public function asController(Production $production, ActionRequest $request): int
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
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
