<?php

/*
 * Author Louis Perez
 * Created on 29-09-2026-15h-03m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
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
use Lorisleiva\Actions\ActionRequest;

class SetArtefactsRecipe extends OrgAction
{
    /**
     * Gives every selected artefact exactly the steps sent: steps they already have are updated, new ones are added,
     * and steps missing from the list are removed together with their raw materials. A step's raw materials go to every
     * artefact unless that artefact has its own list in artefact_raw_materials.
     *
     * @param array{
     *     artefacts: array<int, int>,
     *     steps: array<int, array{
     *         manufacture_task_id: int,
     *         position: int,
     *         units_per_artefact: float|int|string,
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
        $recipe = $steps->mapWithKeys(fn (array $step) => [
            $step['manufacture_task_id'] => [
                'position'           => $step['position'],
                'units_per_artefact' => $step['units_per_artefact'],
            ],
        ])->all();

        $removedTaskIds = $artefact->manufactureTasks()->pluck('manufacture_tasks.id')->diff(array_keys($recipe));

        $artefact->manufactureTasks()->syncWithoutDetaching($recipe);

        foreach ($steps as $step) {
            $recipeStep = ArtefactManufactureTask::where('artefact_id', $artefact->id)
                ->where('manufacture_task_id', $step['manufacture_task_id'])
                ->first();

            $rawMaterials = collect($this->rawMaterialsFor($artefact, $step));

            $recipeStep->rawMaterials()->whereNotIn('raw_material_id', $rawMaterials->pluck('raw_material_id'))->delete();
            $rawMaterials->each(fn (array $rawMaterial) => AttachRawMaterialToRecipeStep::make()->handle($recipeStep, $rawMaterial));
        }

        JobOrderItem::where('artefact_id', $artefact->id)
            ->whereHas('jobOrder', fn ($query) => $query->whereNotIn('state', [JobOrderStateEnum::RECEIVED, JobOrderStateEnum::NOT_RECEIVED]))
            ->each(fn (JobOrderItem $jobOrderItem) => GenerateJobOrderItemTasks::run($jobOrderItem));

        ManufactureTask::whereIn('id', $removedTaskIds)
            ->each(fn (ManufactureTask $manufactureTask) => DetachManufactureTaskFromArtefact::make()->handle($artefact, $manufactureTask));
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
                'distinct',
                Rule::exists('manufacture_tasks', 'id')->where('production_id', $this->production->id),
            ],
            'steps.*.position'                                                   => ['required', 'integer', 'min:1'],
            'steps.*.units_per_artefact'                                         => ['required', 'numeric', 'gt:0'],
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
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'steps.required'                         => __('Add at least one step'),
            'steps.*.manufacture_task_id.required'   => __('Pick a task for every step'),
            'steps.*.manufacture_task_id.distinct'   => __('A task can only be used once in a recipe'),
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
        return back();
    }
}
