<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 21:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrderItemTask\CalculateJobOrderItemTaskQuantities;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\ManufactureTaskSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class CloseManufactureTaskSession extends OrgAction
{
    public function handle(ManufactureTaskSession $session, array $modelData): ManufactureTaskSession
    {
        if ($session->state == ManufactureTaskSessionStateEnum::CLOSED) {
            throw ValidationException::withMessages([
                'state' => __('This task session is already closed'),
            ]);
        }

        $manufactureTask = $session->manufactureTask;

        $session->update([
            'quantity_made'                   => $modelData['quantity_made'],
            'quantity_rejected'               => $modelData['quantity_rejected'] ?? 0,
            'ended_at'                        => now(),
            'state'                           => ManufactureTaskSessionStateEnum::CLOSED,
            'task_work_cost'                  => $manufactureTask->task_work_cost,
            'operative_reward_terms'          => $manufactureTask->operative_reward_terms,
            'operative_reward_allowance_type' => $manufactureTask->operative_reward_allowance_type,
            'operative_reward_amount'         => $manufactureTask->operative_reward_amount,
            'break_minutes'                   => $modelData['break_minutes'] ?? $session->break_minutes ?? 0,
            'activity_type'                   => $modelData['activity_type'] ?? $session->activity_type ?? ManufactureTaskSessionActivityTypeEnum::PRODUCTION,
            'non_productive_reason'           => $modelData['non_productive_reason'] ?? null,
        ]);

        CalculateJobOrderItemTaskQuantities::run($session->jobOrderItemTask);
        CalculateManufactureTaskSessionPay::run($session);

        return $session;
    }

    public function rules(): array
    {
        return [
            'quantity_made'          => ['required', 'numeric', 'min:0'],
            'quantity_rejected'      => ['sometimes', 'numeric', 'min:0'],
            'break_minutes'          => ['sometimes', 'integer', 'min:0'],
            'activity_type'          => ['sometimes', Rule::enum(ManufactureTaskSessionActivityTypeEnum::class)],
            'non_productive_reason'  => [
                Rule::requiredIf(function () {
                    $activityType = $this->get('activity_type');

                    return $activityType && $activityType != ManufactureTaskSessionActivityTypeEnum::PRODUCTION->value;
                }),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->id == $this->manufactureTaskSession->user_id
            || $request->user()->authTo([
                'org-supervisor.'.$this->organisation->id,
                'productions-view.'.$this->organisation->id,
                "productions_operations.{$this->production->id}.view",
                "productions_operations.{$this->production->id}.orchestrate",
            ]);
    }

    private ManufactureTaskSession $manufactureTaskSession;

    public function action(ManufactureTaskSession $session, array $modelData): ManufactureTaskSession
    {
        $this->asAction               = true;
        $this->manufactureTaskSession = $session;
        $this->initialisation($session->organisation, $modelData);

        return $this->handle($session, $this->validatedData);
    }

    public function asController(ManufactureTaskSession $manufactureTaskSession, ActionRequest $request): ManufactureTaskSession
    {
        $this->manufactureTaskSession = $manufactureTaskSession;
        $this->initialisationFromProduction($manufactureTaskSession->production, $request);

        return $this->handle($manufactureTaskSession, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
