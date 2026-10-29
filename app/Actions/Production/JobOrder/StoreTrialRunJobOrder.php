<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrderItem\StoreJobOrderItem;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Models\Production\Production;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * A small batch of an experiment, raised by the development team. On the floor it is a job order like any
 * other, so the artisan works it the same way; what is made goes to the development team's office, not to
 * the warehouse, and never onto the To produce board.
 */
class StoreTrialRunJobOrder extends OrgAction
{
    /**
     * @param array{artefact_id: int, quantity: int, employee_id?: int|null, notes?: string|null} $modelData quantity in units (artefacts)
     */
    public function handle(Production $production, array $modelData): JobOrder
    {
        $artefact = Artefact::where('production_id', $production->id)->findOrFail($modelData['artefact_id']);
        $notes    = trim((string) Arr::get($modelData, 'notes'));

        return DB::transaction(function () use ($production, $modelData, $artefact, $notes) {
            $jobOrder = StoreJobOrder::make()->action($production, [
                'date'        => now(),
                'employee_id' => Arr::get($modelData, 'employee_id') ?? $artefact->artisans()->first()?->id ?? $artefact->artefactDepartment?->artisans()->first()?->id,
            ]);

            $jobOrder->update([
                'internal_notes' => trim(__('Trial run').($notes !== '' ? ': '.$notes : '')),
                'data'           => array_merge($jobOrder->data ?? [], ['trial_run' => true]),
            ]);

            StoreJobOrderItem::make()->action($jobOrder, [
                'artefact_id' => $artefact->id,
                'quantity'    => (int) $modelData['quantity'],
                'data'        => ['batch_code' => $jobOrder->reference.'-'.$artefact->code],
            ]);

            return $jobOrder;
        });
    }

    public function rules(): array
    {
        return [
            'artefact_id' => [
                'required',
                'integer',
                Rule::exists('artefacts', 'id')->where('production_id', $this->production->id)->where('state', ArtefactStateEnum::IN_PROCESS->value),
            ],
            'quantity'    => ['required', 'integer', 'min:1'],
            'employee_id' => ['sometimes', 'nullable', 'integer', Rule::exists('employees', 'id')->where('organisation_id', $this->organisation->id)],
            'notes'       => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'artefact_id.exists' => __('Only an experiment of this factory, not yet launched or cancelled, gets a trial run'),
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $artefact = Artefact::find($this->get('artefact_id'));
        if (!$artefact->manufactureTasks()->exists()) {
            $validator->errors()->add('artefact_id', __('Give :code a recipe first: the floor works from its steps', ['code' => $artefact->code]));
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_rd.{$this->production->id}.edit",
        ]);
    }

    public function action(Production $production, array $modelData): JobOrder
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    public function asController(Production $production, ActionRequest $request): JobOrder
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
