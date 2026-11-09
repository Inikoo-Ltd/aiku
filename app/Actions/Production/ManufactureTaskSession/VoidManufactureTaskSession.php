<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 13:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\Production\JobOrder\FillToOrder;
use App\Actions\OrgAction;
use App\Actions\Production\JobOrderItemTask\CalculateJobOrderItemTaskQuantities;
use App\Actions\Production\JobOrderItemTask\ShareMixPool;
use App\Actions\Production\RawMaterial\TakeMixRawMaterialsFromStock;
use Illuminate\Support\Facades\DB;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\ManufactureTaskSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class VoidManufactureTaskSession extends OrgAction
{
    use WithManufacturePayrollLock;

    public function handle(ManufactureTaskSession $session, array $modelData = []): ManufactureTaskSession
    {
        if ($session->state != ManufactureTaskSessionStateEnum::CLOSED) {
            throw ValidationException::withMessages([
                'state' => __('Only a finished task session can be voided'),
            ]);
        }

        FillToOrder::make()->refuseAfterRelease($session->jobOrderItemTask?->jobOrderItem);

        if ($session->mix_pool_key && ($reference = ShareMixPool::settledLineOf($session))) {
            throw ValidationException::withMessages([
                'state' => __('This mix went into :reference, which is already received or closed, it can no longer be voided', ['reference' => $reference]),
            ]);
        }

        $this->changeInPayWeek($session->production, $session, $session->user_id, 'void', [$session->ended_at], ['state', 'quantity_made', 'pay'], $modelData['lock_reason'] ?? null, fn () => $session->update(['state' => ManufactureTaskSessionStateEnum::VOIDED]));

        if ($session->mix_pool_key) {
            $this->voidSharedMix($session);

            return $session;
        }

        if (!$session->isNonProductive()) {
            CalculateJobOrderItemTaskQuantities::run($session->jobOrderItemTask)->followRecipeWhenNothingRecorded();
            foreach ($session->shares()->where('job_order_item_task_id', '!=', $session->job_order_item_task_id)->with('jobOrderItemTask')->get() as $share) {
                CalculateJobOrderItemTaskQuantities::run($share->jobOrderItemTask)->followRecipeWhenNothingRecorded();
            }
        }

        return $session;
    }

    /**
     * A voided shared mix puts its raw materials back and its lines wait for mix again, taking any leftover of other mixes first.
     */
    private function voidSharedMix(ManufactureTaskSession $session): void
    {
        DB::transaction(function () use ($session) {
            $recipeStep = $session->jobOrderItemTask?->recipeStep;
            if ($recipeStep) {
                TakeMixRawMaterialsFromStock::run($session, $recipeStep, putBack: true);
            }
            foreach ($session->shares()->with('jobOrderItemTask')->get() as $share) {
                CalculateJobOrderItemTaskQuantities::run($share->jobOrderItemTask);
            }
            ShareMixPool::make()->fill($session->production_id, $session->mix_pool_key);
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
            "accounting.{$this->organisation->id}.edit",
        ]);
    }

    public function rules(): array
    {
        return [
            'lock_reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function action(ManufactureTaskSession $session, ?User $editor = null, array $modelData = []): ManufactureTaskSession
    {
        $this->asAction      = true;
        $this->payrollEditor = $editor;
        $this->initialisationFromProduction($session->production, $modelData);

        return $this->handle($session, $this->validatedData);
    }

    public function asController(ManufactureTaskSession $manufactureTaskSession, ActionRequest $request): ManufactureTaskSession
    {
        $this->payrollEditor = $request->user();
        $this->initialisationFromProduction($manufactureTaskSession->production, $request);

        return $this->handle($manufactureTaskSession, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
