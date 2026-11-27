<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 19:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\ArtefactStepTarget;
use App\Models\Production\Production;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * HELP-4982: one recipe step in development (paid at the development rate, no target or band, its sessions collected
 * for a target) while the other steps of the product keep their targets. Set on one product's recipe, or on the same
 * step of every selected product of a family at once. Without a label every step of that task is switched. Steps paid
 * by the hour are refused: development would pay them at the D rate and, having no target to set, nothing would ever end it.
 */
class SetArtefactsStepDevelopment extends OrgAction
{
    /**
     * @param  array{artefacts: array<int, int>, manufacture_task_id: int, label?: string|null, is_in_development: bool}  $modelData
     */
    public function handle(Production $production, array $modelData): int
    {
        $steps = ArtefactManufactureTask::whereIn('artefact_id', $production->artefacts()->whereIn('id', $modelData['artefacts'])->select('id'))
            ->where('manufacture_task_id', $modelData['manufacture_task_id'])
            ->when(array_key_exists('label', $modelData), fn ($query) => ArtefactStepTarget::withLabel($query, $modelData['label']))
            ->where('is_in_development', '!=', $modelData['is_in_development'])
            ->get();

        DB::transaction(function () use ($steps, $modelData) {
            foreach ($steps as $step) {
                $step->update(['is_in_development' => $modelData['is_in_development']]);
            }

            if ($modelData['is_in_development']) {
                Artefact::whereIn('id', $steps->pluck('artefact_id')->unique())->where('is_in_development', false)->get()
                    ->each(fn (Artefact $artefact) => $artefact->update([
                        'development_started_at' => now(),
                        'data'                   => Arr::except($artefact->data ?? [], 'development_review'),
                    ]));
            }
        });

        return $steps->count();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("org-supervisor.{$this->organisation->id}");
    }

    public function rules(): array
    {
        return [
            'artefacts'           => ['required', 'array', 'min:1'],
            'artefacts.*'         => ['integer'],
            'manufacture_task_id' => ['required', 'integer', Rule::exists('manufacture_tasks', 'id')->where('production_id', $this->production->id)->where('is_piece_rate', true)],
            'label'               => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_in_development'   => ['required', 'boolean'],
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
