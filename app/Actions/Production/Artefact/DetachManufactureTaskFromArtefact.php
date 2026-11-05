<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Actions\Production\RawMaterial\Hydrators\RawMaterialHydrateFromOrgStock;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Events\BroadcastManufactureFloorChanged;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DetachManufactureTaskFromArtefact extends OrgAction
{
    /**
     * Takes a step off the recipe; given a task, every step of that task.
     */
    public function handle(Artefact $artefact, ManufactureTask|ArtefactManufactureTask $step): Artefact
    {
        $recipeSteps = $step instanceof ArtefactManufactureTask
            ? collect([$step])
            : $artefact->recipeSteps()->where('manufacture_task_id', $step->id)->get();

        $removedTasks = 0;
        foreach ($recipeSteps as $recipeStep) {
            $removedTasks += $this->removeStep($artefact, $recipeStep);
        }
        RawMaterialHydrateFromOrgStock::dispatchIntermediatesOf($artefact->id);

        if ($removedTasks) {
            BroadcastManufactureFloorChanged::dispatch($artefact->production_id);
        }

        return $artefact;
    }

    private function removeStep(Artefact $artefact, ArtefactManufactureTask $recipeStep): int
    {
        $removedTasks = JobOrderItemTask::where('artefact_manufacture_task_id', $recipeStep->id)
            ->unworked()
            ->whereHas('jobOrderItem', fn ($query) => $query->where('artefact_id', $artefact->id)
                ->whereHas('tasks', fn ($query) => $query->where(fn ($query) => $query->whereNull('artefact_manufacture_task_id')->orWhere('artefact_manufacture_task_id', '!=', $recipeStep->id))))
            ->whereHas('jobOrder', fn ($query) => $query->whereIn('state', JobOrderStateEnum::open()))
            ->delete();

        SetArtefactStepTarget::recordRemoval($recipeStep);

        $artefact->recipeSteps()->whereJsonContains('waits_for', $recipeStep->id)->get()
            ->each(fn (ArtefactManufactureTask $waiting) => $waiting->update(['waits_for' => array_values(array_diff($waiting->waits_for, [$recipeStep->id]))]));

        $recipeStep->delete();

        return $removedTasks;
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

    public function action(Artefact $artefact, ManufactureTask|ArtefactManufactureTask $step): Artefact
    {
        $this->asAction = true;
        $this->initialisation($artefact->organisation, []);

        return $this->handle($artefact, $step);
    }

    public function asController(Artefact $artefact, ArtefactManufactureTask $recipeStep, ActionRequest $request): Artefact
    {
        abort_unless($recipeStep->artefact_id == $artefact->id, 404);
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($artefact, $recipeStep);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
