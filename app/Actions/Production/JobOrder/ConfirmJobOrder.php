<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 13:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Actions\OrgAction;
use App\Actions\Production\Artefact\GetArtefactComplianceStatus;
use App\Enums\Production\Artefact\ArtefactComplianceStatusEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\JobOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class ConfirmJobOrder extends OrgAction
{
    public function handle(JobOrder $jobOrder, array $modelData = []): JobOrder
    {
        if ($jobOrder->state != JobOrderStateEnum::IN_PROCESS) {
            throw ValidationException::withMessages([
                'state' => __('Only a draft job order can be released to the floor'),
            ]);
        }

        if ($jobOrder->jobOrderItems()->count() == 0) {
            throw ValidationException::withMessages([
                'state' => __('Add at least one item before releasing to the floor'),
            ]);
        }

        $nonCompliantArtefacts = [];
        foreach ($jobOrder->jobOrderItems as $jobOrderItem) {
            $status = GetArtefactComplianceStatus::run($jobOrderItem->artefact);
            if ($status['status'] === ArtefactComplianceStatusEnum::PROBLEM) {
                $nonCompliantArtefacts[$jobOrderItem->artefact->code] = $status['problems'][0];
            }
        }

        if ($nonCompliantArtefacts && !($modelData['compliance_override'] ?? false)) {
            throw ValidationException::withMessages([
                'compliance' => collect($nonCompliantArtefacts)
                    ->map(fn ($problem, $code) => "{$code}: {$problem}")
                    ->values()
                    ->all(),
            ]);
        }

        $data = $jobOrder->data;
        if ($nonCompliantArtefacts) {
            $data['compliance_override'] = [
                'user_id'   => request()->user()?->id,
                'at'        => now()->toDateTimeString(),
                'artefacts' => array_keys($nonCompliantArtefacts),
            ];
        }

        $jobOrder->update([
            'state'        => JobOrderStateEnum::CONFIRMED,
            'submitted_at' => now(),
            'confirmed_at' => now(),
            'data'         => $data,
        ]);

        return $jobOrder;
    }

    public function rules(): array
    {
        return [
            'compliance_override' => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function action(JobOrder $jobOrder, array $modelData = []): JobOrder
    {
        $this->asAction = true;
        $this->initialisationFromProduction($jobOrder->production, $modelData);

        return $this->handle($jobOrder, $this->validatedData);
    }

    public function asController(JobOrder $jobOrder, ActionRequest $request): JobOrder
    {
        $this->initialisationFromProduction($jobOrder->production, $request);

        return $this->handle($jobOrder, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
