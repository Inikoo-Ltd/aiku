<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Events\BroadcastManufactureFloorChanged;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DetachManufactureTaskFromArtefact extends OrgAction
{
    public function handle(Artefact $artefact, ManufactureTask $manufactureTask): Artefact
    {
        $artefact->manufactureTasks()->detach($manufactureTask->id);

        $removedTasks = JobOrderItemTask::where('manufacture_task_id', $manufactureTask->id)
            ->where('state', JobOrderItemTaskStateEnum::TODO)
            ->whereDoesntHave('sessions')
            ->whereHas('jobOrderItem', fn ($query) => $query->where('artefact_id', $artefact->id)
                ->whereHas('tasks', fn ($query) => $query->where('manufacture_task_id', '!=', $manufactureTask->id)))
            ->whereHas('jobOrder', fn ($query) => $query->whereIn('state', JobOrderStateEnum::open()))
            ->delete();

        if ($removedTasks) {
            BroadcastManufactureFloorChanged::dispatch($artefact->production_id);
        }

        return $artefact;
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

    public function action(Artefact $artefact, ManufactureTask $manufactureTask): Artefact
    {
        $this->asAction = true;
        $this->initialisation($artefact->organisation, []);

        return $this->handle($artefact, $manufactureTask);
    }

    public function asController(Artefact $artefact, ManufactureTask $manufactureTask, ActionRequest $request): Artefact
    {
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($artefact, $manufactureTask);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
