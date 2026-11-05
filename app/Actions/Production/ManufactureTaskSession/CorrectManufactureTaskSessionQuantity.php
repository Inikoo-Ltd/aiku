<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Oct 2026 10:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrderItem\UpdateJobOrderItem;
use App\Actions\Production\JobOrderItemTask\CalculateJobOrderItemTaskQuantities;
use App\Enums\Production\Artisan\ArtisanPayTypeEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class CorrectManufactureTaskSessionQuantity extends OrgAction
{
    use WithManufacturePayrollLock;

    private ?string $correctedBy = null;

    /**
     * A finished entry is set to what was really made. Brought down, whatever the job grew by on a
     * manager's authorisation shrinks back with it; raised, it needs a reason and fits in what is left
     * on the step. The pay is worked out again either way.
     */
    public function handle(ManufactureTaskSession $session, array $modelData): ManufactureTaskSession
    {
        return DB::transaction(function () use ($session, $modelData) {
            $session = ManufactureTaskSession::lockForUpdate()->find($session->id);

            return $this->changeInPayWeek($session->production, $session, $session->user_id, 'quantity', [$session->ended_at], ['quantity_made', 'job_order_item_task_id', 'manufacture_task_id', 'band_code', 'pay'], $modelData['lock_reason'] ?? null, fn () => $this->correct($session, $modelData));
        });
    }

    private function correct(ManufactureTaskSession $session, array $modelData): ManufactureTaskSession
    {
        $quantity = (float) $modelData['quantity_made'];
        $previous = (float) $session->quantity_made;

        if ($session->state != ManufactureTaskSessionStateEnum::CLOSED || $session->isNonProductive()) {
            $this->refuse(__('Only a finished production entry can be corrected'));
        }
        if ($session->is_combined) {
            $this->refuse(__('A combined batch cannot be corrected, void it and enter it again'));
        }

        $task     = JobOrderItemTask::lockForUpdate()->find($session->job_order_item_task_id);
        $item     = $task->jobOrderItem;
        $jobOrder = $task->jobOrder;

        if (!in_array($jobOrder->state, JobOrderStateEnum::open()) || $item->quantity_received > 0) {
            $this->refuse(__('This job is already being received, its entries can no longer change'));
        }

        $stepId = $modelData['job_order_item_task_id'] ?? null;
        if ($stepId && $stepId != $task->id) {
            return $this->moveToStep($session, $task, $stepId, $quantity, trim($modelData['reason'] ?? ''));
        }

        if ($quantity == $previous) {
            $this->refuse(__('The entry is already :quantity', ['quantity' => $previous]));
        }

        $reason = trim($modelData['reason'] ?? '');
        if ($quantity > $previous) {
            if ($reason === '') {
                $this->refuse(__('Say why the entry goes up'));
            }
            $left = max(0, round((float) $task->quantity_required - (float) $task->quantity_made, 3));
            if (!$task->isRecordedInKg() && $quantity - $previous > $left) {
                $this->refuse(__('Only :left left on :step', ['left' => $left, 'step' => $task->manufactureTask->name]));
            }
        }

        $packSize = $task->packSize();
        if ($packSize && $quantity != floor($quantity)) {
            $this->refuse(__('This step is counted in whole packs of :size, enter a whole number', ['size' => $packSize]));
        }
        $mixBatchKg = $task->mixBatchKg();
        if ($mixBatchKg && !JobOrderItemTask::isWholeMixBatches($quantity, $mixBatchKg)) {
            $this->refuse(JobOrderItemTask::wholeMixBatchesMessage($mixBatchKg));
        }

        $data       = $jobOrder->data ?? [];
        $authorised = collect($data['overproductions'] ?? [])->where('session_id', $session->id);
        $extra      = (float) $authorised->sum('quantity');
        $extraLeft  = round(max(0, $extra - max(0, $previous - $quantity)), 3);
        $takenOff   = $authorised->pluck('taken_off_lines')->filter()->first() ?? [];

        $data['overproductions'] = collect($data['overproductions'] ?? [])
            ->map(fn (array $entry) => ($entry['session_id'] ?? null) == $session->id ? array_merge($entry, ['quantity' => $extraLeft]) : $entry)
            ->reject(fn (array $entry) => ($entry['session_id'] ?? null) == $session->id && $extraLeft <= 0)
            ->values()
            ->all();
        $data['corrections'][] = [
            'session_id'    => $session->id,
            'step'          => $task->manufactureTask->name,
            'artefact_code' => $item->artefact->code,
            'pack_size'     => $packSize,
            'from'          => $previous,
            'to'            => $quantity,
            'reason'        => $reason ?: null,
            'by'            => $this->correctedBy,
            'at'            => now()->toIso8601String(),
        ];
        $jobOrder->update(['data' => $data]);

        $session->quantity_made   = $quantity;
        $session->is_under_target = CloseManufactureTaskSession::make()->isUnderTarget($session);
        $session->save();

        if ($extra > $extraLeft) {
            $required         = round((float) $task->quantity_required - ($extra - $extraLeft), 3);

            $task->setRequiredQuantity($required);
            $quantityBefore = (int) $item->quantity;
            UpdateJobOrderItem::make()->action($item, ['quantity' => max(1, min($quantityBefore, (int) $task->artefactsIn($required)))]);

            if ($takenOff) {
                $stillTakenOff = CloseManufactureTaskSession::make()->giveBackToRestOfOrder($item, $takenOff, $quantityBefore - (int) $item->refresh()->quantity);
                $data          = $jobOrder->refresh()->data;
                $data['overproductions'] = collect($data['overproductions'] ?? [])
                    ->map(fn (array $entry) => ($entry['session_id'] ?? null) == $session->id ? array_merge(Arr::except($entry, 'taken_off_lines'), $stillTakenOff ? ['taken_off_lines' => $stillTakenOff] : []) : $entry)
                    ->all();
                $jobOrder->update(['data' => $data]);
            }
        }

        CalculateJobOrderItemTaskQuantities::run($task->refresh());
        if ($quantity < $previous) {
            $this->undoLineGrowth($session, $task->refresh());
        }

        return CalculateManufactureTaskSessionPay::run($session);
    }

    /**
     * An entry recorded on the wrong step or line, like a mix entered on Making or FSBS-05 work entered on
     * FSBS-01 (HELP-4526), goes to the step really worked, on any open line of the production, with what
     * was made there. The hours stay the artisan's, paid against that step's target. A line that cannot
     * hold it, because it was closed short, grows back to fit: those goods were made on it.
     * ponytail: the grown line takes nothing off the rest of its order, as an authorised overproduction does.
     */
    private function moveToStep(ManufactureTaskSession $session, JobOrderItemTask $from, int $stepId, float $quantity, string $reason): ManufactureTaskSession
    {
        $to = JobOrderItemTask::lockForUpdate()->with(['jobOrder', 'jobOrderItem.artefact', 'manufactureTask'])->find($stepId);
        if (!$to || $to->combined_task_id || $to->jobOrder?->production_id != $session->production_id) {
            $this->refuse(__('Pick a step of an open job line of this production'));
        }
        $toItem   = $to->jobOrderItem;
        $toJob    = $to->jobOrder;
        $fromJob  = $from->jobOrder;
        if ($toJob->state != JobOrderStateEnum::CONFIRMED || $toItem->quantity_received > 0) {
            $this->refuse(__(':reference is not on the floor or is already being received, nothing can be moved to it', ['reference' => $toJob->reference]));
        }
        if ($reason === '') {
            $this->refuse(__('Say why the entry moves'));
        }

        if (collect($fromJob->data['overproductions'] ?? [])->where('session_id', $session->id)->isNotEmpty()) {
            $this->refuse(__('A manager authorised extra on this entry, void it and enter it again'));
        }

        $packSize = $to->packSize();
        if ($packSize && $quantity != floor($quantity)) {
            $this->refuse(__('This step is counted in whole packs of :size, enter a whole number', ['size' => $packSize]));
        }
        $mixBatchKg = $to->mixBatchKg();
        if ($mixBatchKg && !JobOrderItemTask::isWholeMixBatches($quantity, $mixBatchKg)) {
            $this->refuse(JobOrderItemTask::wholeMixBatchesMessage($mixBatchKg));
        }

        $lineBefore = (int) $toItem->quantity;
        $left       = max(0, round((float) $to->quantity_required - (float) $to->quantity_made, 3));
        if (!$to->isRecordedInKg() && $quantity > $left) {
            if (Arr::has($toItem->data ?? [], 'carried_to')) {
                $this->refuse(__('Only :left left on :step, the rest of this line was carried to another job, move the entry there', ['left' => $left, 'step' => $to->manufactureTask->name]));
            }
            $taskUnitsMade = (float) $to->quantity_made + $quantity;
            CloseManufactureTaskSession::make()->growLine($to, $taskUnitsMade, (int) ceil(round($taskUnitsMade / $to->unitsPerArtefact(), 3)));
            $to->refresh();
        }
        $lineAfter = (int) $toItem->refresh()->quantity;

        $correction = [
            'session_id'    => $session->id,
            'step'          => $to->manufactureTask->name,
            'from_step'     => $from->manufactureTask->name,
            'artefact_code' => $toItem->artefact->code,
            'pack_size'     => $packSize,
            'from'          => (float) $session->quantity_made,
            'to'            => $quantity,
            'reason'        => $reason,
            'by'            => $this->correctedBy,
            'at'            => now()->toIso8601String(),
        ];
        if ($from->job_order_item_id != $toItem->id) {
            $correction['from_artefact_code'] = $from->jobOrderItem->artefact->code;
        }
        if ($fromJob->id != $toJob->id) {
            $correction['from_job'] = $fromJob->reference;
            $correction['to_job']   = $toJob->reference;
        }
        if ($lineAfter != $lineBefore) {
            $correction['line_from'] = $lineBefore;
            $correction['line_to']   = $lineAfter;
        }
        foreach ($fromJob->id == $toJob->id ? [$toJob] : [$fromJob, $toJob] as $jobOrder) {
            $data                  = $jobOrder->refresh()->data ?? [];
            $data['corrections'][] = $correction;
            if ($jobOrder->id == $toJob->id && $lineAfter != $lineBefore) {
                $data['line_growths'][] = ['session_id' => $session->id, 'job_order_item_id' => $toItem->id, 'from' => $lineBefore, 'to' => $lineAfter];
            }
            $jobOrder->update(['data' => $data]);
        }

        $session->fill([
            'job_order_item_task_id' => $to->id,
            'manufacture_task_id'    => $to->manufacture_task_id,
            'quantity_made'          => $quantity,
            'quantity_rejected'      => 0,
        ]);
        if (in_array($session->activity_type, [ManufactureTaskSessionActivityTypeEnum::PRODUCTION, ManufactureTaskSessionActivityTypeEnum::DEVELOPMENT])) {
            $session->activity_type = $toItem->artefact->isPaidAsDevelopment() ? ManufactureTaskSessionActivityTypeEnum::DEVELOPMENT : ManufactureTaskSessionActivityTypeEnum::PRODUCTION;
        }
        $session->setRelation('jobOrderItemTask', $to);
        $session->unsetRelation('manufactureTask');
        $session->standard_rate = $session->recipeStandardRate();
        CloseManufactureTaskSession::make()->applyPayTerms($session, $session->artisan_pay_type ?? ArtisanPayTypeEnum::PIECE_RATE);
        $session->is_under_target = CloseManufactureTaskSession::make()->isUnderTarget($session);
        $session->save();

        CalculateJobOrderItemTaskQuantities::run($from->refresh());
        $this->undoLineGrowth($session, $from->refresh());
        $from->refresh()->followRecipeWhenNothingRecorded();
        CalculateJobOrderItemTaskQuantities::run($to->refresh());

        return CalculateManufactureTaskSessionPay::run($session);
    }

    /**
     * A line an entry's move made grow (HELP-4526) comes back down when that entry is moved away again or
     * lowered, as far as what is still made on the step allows, so nobody is asked to make goods never ordered.
     * ponytail: each growth comes back to its own starting size; two entries growing one line and undone
     * out of order can leave the second growth's base on the line.
     */
    private function undoLineGrowth(ManufactureTaskSession $session, JobOrderItemTask $step): void
    {
        $jobOrder = $step->jobOrder->refresh();
        $data     = $jobOrder->data ?? [];
        $growth   = collect($data['line_growths'] ?? [])
            ->first(fn (array $entry) => $entry['session_id'] == $session->id && $entry['job_order_item_id'] == $step->job_order_item_id);
        if (!$growth || $step->isRecordedInKg()) {
            return;
        }

        $item     = $step->jobOrderItem->refresh();
        $needed   = (int) ceil(round((float) $step->quantity_made / $step->unitsPerArtefact(), 3));
        $quantity = max((int) $growth['from'], min((int) $item->quantity, $needed));
        if ($quantity < $item->quantity) {
            UpdateJobOrderItem::make()->action($item, ['quantity' => $quantity]);
        }

        $data['line_growths'] = collect($data['line_growths'])
            ->map(fn (array $entry) => $entry == $growth ? array_merge($entry, ['to' => $quantity]) : $entry)
            ->reject(fn (array $entry) => $entry['to'] <= $entry['from'])
            ->values()
            ->all();
        $jobOrder->update(['data' => $data]);
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['quantity_made' => $message]);
    }

    public function rules(): array
    {
        return [
            'quantity_made'          => ['required', 'numeric', 'min:0'],
            'job_order_item_task_id' => ['sometimes', 'nullable', 'integer'],
            'reason'                 => ['sometimes', 'nullable', 'string', 'max:500'],
            'lock_reason'            => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
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

    public function action(ManufactureTaskSession $session, array $modelData, ?string $correctedBy = null, ?User $editor = null): ManufactureTaskSession
    {
        $this->asAction      = true;
        $this->correctedBy   = $correctedBy;
        $this->payrollEditor = $editor;
        $this->initialisationFromProduction($session->production, $modelData);

        return $this->handle($session, $this->validatedData);
    }

    public function asController(ManufactureTaskSession $manufactureTaskSession, ActionRequest $request): ManufactureTaskSession
    {
        $this->initialisationFromProduction($manufactureTaskSession->production, $request);
        $this->correctedBy   = $request->user()->contact_name ?: $request->user()->username;
        $this->payrollEditor = $request->user();

        return $this->handle($manufactureTaskSession, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
